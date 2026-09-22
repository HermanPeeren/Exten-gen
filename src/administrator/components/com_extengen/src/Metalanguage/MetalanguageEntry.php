<?php

/**
 * @package     Extengen
 * @subpackage  Metalanguage
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Metalanguage;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One metalanguage this site can write a project in: step 3.4.
 *
 * A key and a version identify it, and both are needed - the plan settled that
 * a site holds as many languages as have been imported and that two versions of
 * one language sit beside each other, so a project recording only the key would
 * not say which forms open it.
 *
 * **ER1 is one of these too, and that is the point.** Until 3.5 turns it into a
 * generated package, the entry for it is the forms this component ships: same
 * list, same dropdown, same binding on a project. `isBuiltIn()` is the only
 * thing that tells them apart, and it exists so that the two things that
 * genuinely differ - where the root form is and where the reference table comes
 * from - have somewhere to ask. When 3.5 lands, ER1 becomes an imported row
 * like any other and nothing above this changes.
 *
 * @since  1.1.0
 */
final class MetalanguageEntry
{
    /**
     * What a project written before there were metalanguages is written in.
     *
     * An empty binding means ER1, because that is what every project in the
     * database was made with. Reading it as "no language" would make every
     * existing project unopenable on the day this shipped.
     *
     * @since  1.1.0
     */
    public const BUILT_IN_KEY = 'ER1';

    /**
     * @param  string  $key          The language's name as it appears in a path.
     * @param  string  $version      Which version of it.
     * @param  string  $name         What it calls itself.
     * @param  string  $root         The classifier a model of it opens at, or '' for the built-in.
     * @param  string  $formRoot     Where its forms live, from the site root, with a trailing slash.
     * @param  string  $languageFile Its language file, relative to `formRoot`.
     * @param  bool    $builtIn      Whether this is the set the component ships rather than an import.
     * @param  int     $id           Its row id, or 0 for the built-in, which is a row nowhere.
     *
     * @since  1.1.0
     */
    public function __construct(
        public readonly string $key,
        public readonly string $version,
        public readonly string $name,
        public readonly string $root,
        public readonly string $formRoot,
        public readonly string $languageFile,
        public readonly bool $builtIn = false,
        public readonly int $id = 0
    ) {
    }

    /**
     * ER1, as this component ships it.
     *
     * @since  1.1.0
     */
    public static function builtIn(): self
    {
        return new self(
            self::BUILT_IN_KEY,
            '1.0',
            'ER1',
            'Project',
            'administrator/components/com_extengen/forms/',
            '',
            true
        );
    }

    /**
     * One row of `#__extengen_metalanguages`.
     *
     * @param  object  $row  As the database hands it back.
     *
     * @since  1.1.0
     */
    public static function fromRow(object $row): self
    {
        return new self(
            (string) ($row->lang_key ?? ''),
            (string) ($row->version ?? ''),
            (string) ($row->name ?? ''),
            (string) ($row->root ?? ''),
            (string) ($row->form_root ?? ''),
            (string) ($row->language_file ?? ''),
            false,
            (int) ($row->id ?? 0)
        );
    }

    /**
     * Whether this is the set the component ships.
     *
     * @since  1.1.0
     */
    public function isBuiltIn(): bool
    {
        return $this->builtIn;
    }

    /**
     * How one language is named where somebody picks one.
     *
     * @since  1.1.0
     */
    public function label(): string
    {
        return $this->name . ' ' . $this->version;
    }

    /**
     * The value a project stores to say it is written in this one.
     *
     * @since  1.1.0
     */
    public function binding(): string
    {
        return $this->key . '|' . $this->version;
    }

    /**
     * Whether a stored binding names this entry.
     *
     * An empty binding is the built-in's, for the reason `BUILT_IN_KEY` gives.
     *
     * @since  1.1.0
     */
    public function answersTo(string $key, string $version): bool
    {
        if ($key === '') {
            return $this->builtIn;
        }

        return $this->key === $key && $this->version === $version;
    }

    /**
     * The site-relative path of the form a model of this language opens at.
     *
     * `project_er1.xml` for the built-in, which is the model half of the old
     * `project.xml`; an imported one's is named after its root classifier, the
     * way its package names every other form. The two are deliberately the
     * same shape, so that 3.5 replacing ER1 with a package changes this method
     * and nothing else.
     *
     * @since  1.1.0
     */
    public function rootFormPath(): string
    {
        if ($this->builtIn) {
            return $this->formRoot . 'project_er1.xml';
        }

        return $this->formRoot . 'forms/' . lcfirst($this->root) . '.xml';
    }

    /**
     * The site-relative path of its reference table, or '' for the built-in.
     *
     * The built-in has none: ER1's table is `Reference\Er1::TABLE`, PHP this
     * component ships, and that is exactly the difference 3.5 removes.
     *
     * @since  1.1.0
     */
    public function referenceTablePath(): string
    {
        return $this->builtIn ? '' : $this->formRoot . 'forms/references.json';
    }
}
