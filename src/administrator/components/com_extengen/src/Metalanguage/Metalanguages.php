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
 * What this component brings to the shared metalanguage store.
 *
 * One thing now: the table. **There is no built-in language any more** - 3.5
 * modelled ER1 in LionCore M3, kept the generated set, and this component ships
 * it as a package that installs the way any imported language does.
 *
 * That is the whole point of the step rather than a tidy-up. A shipped language
 * loaded straight from `forms/` was a language exempt from everything an
 * imported one goes through: no manifest, no hashes, no refusal when a form is
 * missing. ER1 goes through the same reader as everything else now, and if its
 * package were damaged the import would say so instead of the component
 * rendering an empty box three levels down.
 *
 * `MetalanguageEntry::builtIn()` and the `project_er1.xml` it pointed at are
 * gone with it.
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
     * The half of a project's form that is this component's own.
     *
     * 3.2 recorded why it is not generated: alias, published, access, catid,
     * ordering and params are not derivable from a language at all. It is the
     * one form file this component still ships, and it is named here so that
     * the model loading it and the view reading it cannot drift apart.
     *
     * @since  1.4.0
     */
    public const CHROME_FORM = 'administrator/components/com_extengen/forms/project_chrome.xml';

    /**
     * The language every project made before 3.4 is written in.
     *
     * Their binding was empty, and an empty binding meant "the forms this
     * component ships" - which were ER1's. The install script fills it in
     * rather than leaving a fallback to mean it, because a fallback is a second
     * answer to "which language" and 3.4 exists so that there is one.
     *
     * @since  1.4.0
     */
    public const SHIPPED = 'ER1';

    /**
     * Every language a project may be written in.
     *
     * No built-in is passed. A site that has somehow lost the ER1 package has
     * an empty list, and the screens say so - which is true, and better than
     * offering a language whose forms are not there.
     *
     * @since  1.2.0
     */
    public static function catalogue(DatabaseInterface $database): MetalanguageCatalogue
    {
        return new MetalanguageCatalogue($database, self::TABLE);
    }

    /**
     * The language a project says it is written in, or null.
     *
     * Null is a real answer now, where it used to fall back to the built-in:
     * a project bound to a language this site does not have cannot be opened
     * with anything, and saying so beats opening it with somebody else's forms
     * and letting a save reshape the model to fit them.
     *
     * @since  1.2.0
     */
    public static function forProject(DatabaseInterface $database, string $key, string $version): ?MetalanguageEntry
    {
        return self::catalogue($database)->forRecord($key, $version);
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
