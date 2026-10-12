<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Diagram;

use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A project's datamodel as a PlantUML entity-relationship diagram.
 *
 * Taken out of the ERD view, where it was written into `display()` between an
 * `fopen()` and an `echo` and so could not be asked anything - which is how it
 * kept comparing a field's kind against `'property'` and `'reference'` for a
 * year after 3.5 renamed them. `FieldKind` fixed the generators and missed this,
 * because this is not a generator: every project saved through the screens
 * since then has drawn as boxes with blank types and no lines between them,
 * while the one project seeded from a pre-3.5 fixture drew perfectly.
 *
 * So a field's kind is asked of `FieldKind`, like everywhere else, and the text
 * is the same text the view always produced for a model it could read.
 *
 * @since  1.6.4
 */
final class EntityRelationshipUml
{
    /**
     * The PlantUML source for a project's datamodel.
     *
     * @param  object  $project  The project as stored: `datamodel` holds the entities.
     *
     * @since  1.6.4
     */
    public static function of(object $project): string
    {
        $entities = \is_object($project->datamodel ?? null) || \is_array($project->datamodel ?? null)
            ? (array) $project->datamodel
            : [];

        // A map of entity_id to name, and whether each is a value object.
        $entityNameMap = [];
        $embeddableMap = [];

        foreach ($entities as $entity) {
            $entityNameMap[$entity->entity_id] = ucfirst($entity->entity_name);
            $embeddableMap[$entity->entity_id] = property_exists($entity, 'isvalueobject');
        }

        $umlCreate   = [];
        $umlCreate[] = "@startuml
' hide the spot
' hide circle

' avoid problems with angled crows feet
skinparam linetype ortho

skinparam class{
            BackgroundColor BlanchedAlmond
        }
skinparam ClassBackgroundColor<<value object>> LightCyan
";
        $umlRef = [];

        foreach ($entities as $entity) {
            $entityName = $entity->entity_name;

            $valueObject = '';
            $id          = '';

            if (property_exists($entity, 'isvalueobject')) {
                $valueObject = ' <<(V,DeepSkyBlue)value object>> ';
            } else {
                $id = "\n  *id : number <<generated>>\n  --";
            }

            // The tabs are the old view's indentation, kept so its text is unchanged.
            $umlCreate[] = "\t\t\t\n\t\t\tentity \"" . $entityName . '" ' . $valueObject . ' {' . $id . "\n  ";

            $references = [];

            foreach ((array) ($entity->field ?? []) as $field) {
                $type = '';

                if (FieldKind::isProperty($field)) {
                    $type = (string) (FieldKind::property($field)->type ?? '');
                } elseif (FieldKind::isReference($field)) {
                    $reference = FieldKind::reference($field);
                    $target    = (string) ($reference->reference ?? '');
                    $name      = $entityNameMap[$target] ?? '';

                    $type         = $name . '  <<FK>>';
                    $references[] = [
                        'name'                  => $name,
                        'ismultiple'            => $reference->ismultiple ?? false,
                        'isrequired'            => $reference->isrequired ?? false,
                        'referencesValueobject' => $embeddableMap[$target] ?? false,
                    ];
                }

                $umlCreate[] = ' *' . strtolower((string) $field->field_name) . ' : ' . $type;
            }

            foreach ($references as $reference) {
                if ($reference['referencesValueobject']) {
                    // It refers to an embeddable, a value object.
                    $multipleSymbol = $reference['ismultiple'] ? 'n' : '1';
                    $requiredSymbol = $reference['isrequired'] ? '1' : '0';
                    $umlRef[]       = $entityName . ' *-- "' . $requiredSymbol . '..' . $multipleSymbol . '" ' . $reference['name'];
                } else {
                    // Only when this entity owns the reference: many-to-one, not
                    // one-to-many (except with embeddables).
                    // todo: model the relation from both sides
                    // todo: not multiple AND required (||) works in PlantUML online, not here
                    $multipleSymbol = $reference['ismultiple'] ? '}' : '|';
                    $requiredSymbol = $reference['isrequired'] ? '|' : 'o';
                    $umlRef[]       = $reference['name'] . ' ' . $multipleSymbol . $requiredSymbol . '..o{ ' . $entityName;
                }
            }

            $umlCreate[] = "}\n\t\t\t\n\t\t\t";
        }

        $umlCreate[] = implode("\n", $umlRef);
        $umlCreate[] = "'hide <<embedded object>> circle";
        $umlCreate[] = '@enduml';

        return implode("\n", $umlCreate);
    }
}
