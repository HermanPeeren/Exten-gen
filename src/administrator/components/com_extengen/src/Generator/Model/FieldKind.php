<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Model;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Whether a field holds a value or points at another entity, and where.
 *
 * ER1's `Field` is abstract over `Property` and `EntityReferenceField`, and a
 * stored field says which it is in a discriminator and carries the rest in a
 * subform named for the subtype. Fifteen places used to ask that question by
 * comparing `field_type` against the lower-case `'property'` and `'reference'`
 * and reading `->property` and `->reference`.
 *
 * **Those were the hand-written form's names, and 3.5 deleted the hand-written
 * forms.** A generated ER1 writes the discriminator's options from the subtype
 * *concept names* - `Property`, `EntityReferenceField` - and the subform from
 * the same name with a small first letter. So every project modelled since 3.5
 * stored something no generator recognised, and generated a component with no
 * relations in it: no foreign key, no join, no dropdown, and no error anywhere.
 * Nothing caught it because every model in the suite predates 3.5.
 *
 * So the question is asked here, once.
 *
 * **The names come from the language.** `Property` and `EntityReferenceField`
 * are concepts ER1 declares and `field_type` is a feature it declares, which
 * `FieldKindTest` checks against the shipped package rather than trusting these
 * constants - and which the ancestry guard then protects, because a derived
 * language may not rename a concept or a feature. The old spellings are listed
 * beside them as what they are: the names the hand-written forms used, kept so
 * that a model written before 3.5 still generates.
 *
 * **The payload key is derived rather than named.** Meta-gen writes the subform
 * as the subtype's name with a small first letter, so that is what is looked
 * for first; the other spellings follow. Deriving it is the half that stops
 * this being wrong again the next time a subtype is added - a new one needs
 * nothing here at all unless a generator has to *mean* something by it.
 *
 * @since  1.4.0
 */
final class FieldKind
{
    /**
     * The feature a field says its kind in.
     *
     * @since  1.4.0
     */
    public const DISCRIMINATOR = 'field_type';

    /**
     * The subtype whose payload is a stored value.
     *
     * @since  1.4.0
     */
    public const PROPERTY = 'Property';

    /**
     * The subtype whose payload points at another entity.
     *
     * @since  1.4.0
     */
    public const REFERENCE = 'EntityReferenceField';

    /**
     * What the hand-written forms called each subtype, before 3.5.
     *
     * Not aliases in general - each of these is exactly one form's field name,
     * and they are here so that a project stored under those forms keeps
     * generating what it always generated. The golden fixtures are all such
     * projects, which is why they are byte-identical either way.
     *
     * Public because converting between the two spellings is not only this
     * class's business: the test corpus has to be able to produce both, and a
     * rule that broke once by being written down twice is written down once.
     *
     * @var    array<string, string>  Subtype => the name the old forms used.
     * @since  1.4.0
     */
    public const LEGACY = [
        self::PROPERTY  => 'property',
        self::REFERENCE => 'reference',
    ];

    /**
     * Whether this field holds a stored value.
     *
     * @since  1.4.0
     */
    public static function isProperty(?object $field): bool
    {
        return self::is($field, self::PROPERTY);
    }

    /**
     * Whether this field points at another entity.
     *
     * @since  1.4.0
     */
    public static function isReference(?object $field): bool
    {
        return self::is($field, self::REFERENCE);
    }

    /**
     * The stored value's own node, or null when this field is not one.
     *
     * @since  1.4.0
     */
    public static function property(?object $field): ?object
    {
        return self::isProperty($field) ? self::payload($field, self::PROPERTY) : null;
    }

    /**
     * The reference's own node, or null when this field is not one.
     *
     * @since  1.4.0
     */
    public static function reference(?object $field): ?object
    {
        return self::isReference($field) ? self::payload($field, self::REFERENCE) : null;
    }

    /**
     * Whether the field's discriminator names this subtype.
     *
     * Compared without case, because the only difference between what a
     * generated form writes and what the hand-written one wrote is the first
     * letter - and a comparison that cared would be one more thing to get wrong
     * for no reading anybody would do.
     *
     * @since  1.4.0
     */
    private static function is(?object $field, string $subtype): bool
    {
        if ($field === null) {
            return false;
        }

        $value = $field->{self::DISCRIMINATOR} ?? null;

        if (!\is_scalar($value)) {
            return false;
        }

        $value = strtolower(trim((string) $value));

        return $value === strtolower($subtype) || $value === self::LEGACY[$subtype];
    }

    /**
     * Where a subtype keeps what it holds.
     *
     * Derived first and named second. A field carrying neither is a field
     * somebody is in the middle of writing rather than an impossibility, and
     * an empty object lets a caller ask it the questions it was going to ask
     * without checking for null at every one.
     *
     * @since  1.4.0
     */
    private static function payload(object $field, string $subtype): object
    {
        foreach ([lcfirst($subtype), $subtype, self::LEGACY[$subtype]] as $key) {
            if (isset($field->{$key}) && \is_object($field->{$key})) {
                return $field->{$key};
            }
        }

        return new \stdClass();
    }
}
