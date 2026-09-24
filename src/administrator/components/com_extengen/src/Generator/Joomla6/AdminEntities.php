<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Generator\Joomla6;

use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;
use Yepr\Component\Extengen\Administrator\Generator\RuleDrivenGenerator;

/**
 * The database: one table per entity, plus the junctions between them.
 *
 * The Table classes come from a rule, because a Table class is a template
 * rendered once per entity. The sql does not, and this is where the line
 * between a rule and an emitter falls: `install.mysql.utf8.sql` is a single
 * file assembled from every entity at once, with a junction section that can
 * only be written after all of them have been seen. There is no template, no
 * per-node output path, and nothing for a rule to say.
 *
 * It is also the only file here whose *contents* are a language - sql - with
 * its own quoting, which is exactly what the emitters exist for.
 *
 * @since  0.8.0
 */
class AdminEntities extends RuleDrivenGenerator
{
	/**
	 * The model's data types, in MySQL.
	 *
	 * todo: other types, like JSON, and attributes and defaults.
	 * N.B. Bool and Boolean are not native MySQL types.
	 *
	 * @var    array<string, string>
	 * @since  1.1.0
	 */
	private const SQL_TYPES = [
		'Integer'    => 'int NOT NULL DEFAULT 0',
		'Boolean'    => 'tinyint unsigned NOT NULL DEFAULT 0',
		'Text'       => 'text',
		'Decimal'    => 'decimal(10,2)',
		'Currency'   => 'decimal(10,2)',
		'Float'      => 'float',
		'Short_Text' => 'varchar(255)',
		'Time'       => 'time',
		'Date'       => 'date',
		'DateTime'   => "datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
		'File'       => 'varchar(255)',
		'Link'       => 'varchar(255)',
		'Image'      => 'varchar(255)',
	];

	/**
	 * The rules that produce the Table classes.
	 *
	 * @return  string
	 *
	 * @since   1.1.0
	 */
	public function rulePrefix(): string
	{
		return 'admin.entity.';
	}

	/**
	 * The install and uninstall sql.
	 *
	 * Nothing is opened until every statement has been collected, so a run that
	 * fails part way through leaves no half-written schema behind.
	 *
	 * @return  string[]
	 *
	 * @since   1.1.0
	 */
	protected function generateBeyondRules(): array
	{
		$project       = $this->AST;
		$componentName = ucfirst($this->componentName);
		$prefix        = '#__' . strtolower($componentName) . '_';

		$entities = [];

		foreach ($project->datamodel as $entity) {
			$entities[$entity->entity_id] = $entity;
		}

		$log        = ['generated install.mysql.utf8.sql sql-file', 'generated uninstall.mysql.utf8.sql sql-file'];
		$create     = [];
		$drop       = [];
		$junctions  = [];

		foreach ($project->datamodel as $entity) {
			// Only entities have a table of their own; an embeddable is stored
			// inside the entity that refers to it.
			if (property_exists($entity, 'isvalueobject')) {
				continue;
			}

			$entityName = ucfirst($entity->entity_name);

			// Singular, because the inflector only works for English names.
			$tableName = $prefix . strtolower($entityName);

			// Every table has an auto increment id, and it is the primary key.
			$columns = ['`id` bigint UNSIGNED NOT NULL AUTO_INCREMENT'];

			foreach ($entity->field as $field) {
				$column = $this->column($field, $entity, $entities, $junctions);

				if ($column !== null) {
					$columns[] = $column;
				}
			}

			$columns[] = 'PRIMARY KEY (`id`)';

			$create[] = "CREATE TABLE IF NOT EXISTS `$tableName` (\n"
				. implode(",\n", $columns)
				. "\n)  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;";
			$drop[]   = "DROP TABLE IF EXISTS `$tableName`;";

			$log[] = 'generated CREATE TABLE sql statement for ' . $tableName . ' in sql-file';
		}

		foreach ($this->uniqueJunctions($junctions) as $junction) {
			$tableName = $prefix . $junction[0] . '_' . $junction[1];

			$id1 = '`' . $junction[0] . '_id`';
			$id2 = '`' . $junction[1] . '_id`';

			$create[] = "CREATE TABLE IF NOT EXISTS `$tableName` (\n"
				. $id1 . " bigint(20) UNSIGNED,\n"
				. $id2 . " bigint(20) UNSIGNED,\n"
				. "PRIMARY KEY ($id1, $id2)\n"
				. ')  ENGINE=InnoDB DEFAULT COLLATE utf8mb4_unicode_ci;';
			$drop[]   = "DROP TABLE IF EXISTS `$tableName`;";

			$log[] = 'generated CREATE TABLE sql statement for ' . $tableName . ' in sql-file';
		}

		$sqlPath = 'administrator/components/com_' . strtolower($componentName) . '/sql/';

		$this->addFile($sqlPath . 'install.mysql.utf8.sql', implode("\n\n", $create));
		$this->addFile($sqlPath . 'uninstall.mysql.utf8.sql', implode("\n", $drop));

		return $log;
	}

	/**
	 * One field's column definition, or null when the field needs no column of its own.
	 *
	 * A many-to-many reference needs a junction table rather than a column, and
	 * notes one in $junctions as a side effect.
	 *
	 * @param   object                     $field      The field.
	 * @param   object                     $entity     The entity it belongs to.
	 * @param   array<string|int, object>  $entities   Every entity, by id.
	 * @param   array<int, string[]>       $junctions  Collected junctions, added to.
	 *
	 * @return  ?string
	 *
	 * @since   1.1.0
	 */
	private function column(object $field, object $entity, array $entities, array &$junctions): ?string
	{
		if (FieldKind::isProperty($field)) {
			return '`' . $field->field_name . '` '
				. (self::SQL_TYPES[FieldKind::property($field)->type ?? ''] ?? 'text');
		}

		if (!FieldKind::isReference($field)) {
			return null;
		}

		$referred = $entities[FieldKind::reference($field)->reference];

		// An embeddable is stored inline, as text.
		if (property_exists($referred, 'isvalueobject')) {
			return '`' . strtolower($field->field_name) . '` TEXT';
		}

		if (!property_exists(FieldKind::reference($field), 'ismultiple')) {
			// n:1 - a foreign key on this table.
			return '`' . strtolower($referred->entity_name) . '_id` bigint(20) UNSIGNED';
		}

		// n:n - a junction table, which is written once both sides are known.
		$junctions[] = [strtolower($entity->entity_name), strtolower($referred->entity_name)];

		return null;
	}

	/**
	 * The junctions, each named once.
	 *
	 * A junction from Speaker to Presentation and one from Presentation to
	 * Speaker are the same table. Sorting the pair and then taking the distinct
	 * ones is what says so.
	 *
	 * @param   array<int, string[]>  $junctions  Every junction noted, in both directions.
	 *
	 * @return  array<int, string[]>
	 *
	 * @since   1.1.0
	 */
	private function uniqueJunctions(array $junctions): array
	{
		$sorted = array_map(
			static function (array $pair): array {
				sort($pair);

				return $pair;
			},
			$junctions
		);

		$unique = array_unique(array_map(
			static fn (array $pair): string => $pair[0] . $pair[1],
			$sorted
		));

		return array_values(array_intersect_key($sorted, $unique));
	}
}
