<?php

/**
 * @package     Extengen
 * @subpackage  Tests
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Support;

use Yepr\Component\Extengen\Administrator\Generator\Model\FieldKind;

/**
 * How a model looks once a generated form has written it.
 *
 * Every model this repository keeps - the golden fixtures, the seeded projects,
 * the models the browser specs open - predates 3.5, when ER1 stopped being a
 * hand-written set of forms and became a generated language. A generated form
 * spells the choice between `Property` and `EntityReferenceField` with the
 * *concept names*, and stores what the user typed under the concept name with a
 * small first letter. The hand-written forms it replaced had said `property` and
 * `reference`.
 *
 * So there were two spellings and no fixture in the second one, which is why
 * every generator could read the wrong key for months without a single test
 * going red. This class is the missing half of the corpus: it converts a model
 * from the old spelling to the one the screens produce today, so both can be
 * put through the same checks.
 *
 * It lives here rather than in either caller because there are two - the unit
 * test that compares the output of both spellings, and `tools/seed-project.php`,
 * which puts the converted model on the development site for Cypress to
 * generate from. A rule that broke once by being written down in several places
 * is written down in one.
 *
 * @since  1.4.0
 */
final class FormSpelling
{
    /**
     * The same model, spelled the way a generated form writes it.
     *
     * A model already in the new spelling comes back unchanged, so this is safe
     * to apply twice - which matters for the seeder, where the alternative is a
     * site whose state depends on how often somebody ran a script.
     *
     * @param   array<string, mixed>  $model  A model in either spelling.
     *
     * @return  array<string, mixed>  The same model, in the new one.
     *
     * @since   1.4.0
     */
    public static function asGeneratedFormsWriteIt(array $model): array
    {
        foreach ($model['datamodel'] ?? [] as $entityKey => $entity) {
            foreach ($entity['field'] ?? [] as $fieldKey => $field) {
                $model['datamodel'][$entityKey]['field'][$fieldKey] = self::field($field);
            }
        }

        return $model;
    }

    /**
     * One field: the discriminator, and the payload that has to move with it.
     *
     * @param   array<string, mixed>  $field
     *
     * @return  array<string, mixed>
     *
     * @since   1.4.0
     */
    private static function field(array $field): array
    {
        $kind = strtolower(trim((string) ($field[FieldKind::DISCRIMINATOR] ?? '')));

        foreach ([FieldKind::PROPERTY, FieldKind::REFERENCE] as $subtype) {
            if ($kind !== strtolower($subtype) && $kind !== self::legacyNameOf($subtype)) {
                continue;
            }

            $field[FieldKind::DISCRIMINATOR] = $subtype;

            // Where the payload now lives, derived the same way the form
            // generator derives the subform's name.
            $to   = lcfirst($subtype);
            $from = self::legacyNameOf($subtype);

            if ($to !== $from && \array_key_exists($from, $field)) {
                $field[$to] = $field[$from];
                unset($field[$from]);
            }

            return $field;
        }

        return $field;
    }

    /**
     * What the hand-written forms called this subtype, before 3.5.
     *
     * Read out of `FieldKind` rather than repeated. The old names are the whole
     * subject of this file, so a second copy of them here would be the same
     * mistake in a smaller font.
     *
     * @since  1.4.0
     */
    private static function legacyNameOf(string $subtype): string
    {
        return FieldKind::LEGACY[$subtype] ?? strtolower($subtype);
    }
}
