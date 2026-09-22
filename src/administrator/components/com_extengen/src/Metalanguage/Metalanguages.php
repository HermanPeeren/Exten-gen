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

use Joomla\Database\DatabaseInterface;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageCatalogue;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageEntry;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageImporter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What this component brings to the shared metalanguage store: step 3.4.
 *
 * The store itself is the library's, because Gen-gen keeps the same kind of
 * list. Exactly three things differ between the two, and they are all here:
 * the table the imported languages are kept in, the language this component
 * ships, and where that shipped language's forms are.
 *
 * **ER1 is the shipped one, and it is an ordinary entry.** Same list, same
 * dropdown, same binding on a project - `isBuiltIn()` is the only thing that
 * tells it apart, and it exists so that the two things which genuinely differ
 * have somewhere to ask: its root form is `project_er1.xml` rather than a name
 * derived from a package, and it has no generated reference table because
 * ER1's is `Reference\Er1::TABLE`, PHP this component ships.
 *
 * 3.5 removes both differences by generating ER1 from LionCore M3 and keeping
 * the generated set. When it does, `builtIn()` stops being passed to the
 * catalogue and nothing above this changes.
 *
 * @since  1.2.0
 */
final class Metalanguages
{
    /**
     * Where this component keeps the languages it has imported.
     *
     * @since  1.2.0
     */
    public const TABLE = '#__extengen_metalanguages';

    /**
     * ER1, as this component ships it.
     *
     * @since  1.2.0
     */
    public static function builtIn(): MetalanguageEntry
    {
        return new MetalanguageEntry(
            'ER1',
            '1.0',
            'ER1',
            'Project',
            'administrator/components/com_extengen/forms/',
            '',
            true,
            0,
            [],
            'project_er1.xml'
        );
    }

    /**
     * Every language a project may be written in.
     *
     * @since  1.2.0
     */
    public static function catalogue(DatabaseInterface $database): MetalanguageCatalogue
    {
        return new MetalanguageCatalogue($database, self::TABLE, self::builtIn());
    }

    /**
     * The language a project says it is written in.
     *
     * Never null here, whatever the catalogue's signature allows: this
     * component ships one, so the fallback always has something to fall back
     * to. A project whose language has been removed opens through ER1 rather
     * than not opening, which is the lesser wrong - somebody needs to see the
     * project in order to fix it.
     *
     * @since  1.2.0
     */
    public static function forProject(DatabaseInterface $database, string $key, string $version): MetalanguageEntry
    {
        return self::catalogue($database)->forRecord($key, $version) ?? self::builtIn();
    }

    /**
     * What installs an imported language and records it.
     *
     * @since  1.2.0
     */
    public static function importer(DatabaseInterface $database, string $siteRoot): MetalanguageImporter
    {
        return new MetalanguageImporter($database, self::TABLE, $siteRoot);
    }
}
