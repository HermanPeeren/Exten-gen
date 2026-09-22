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

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Importing a metalanguage: its files, and then its row.
 *
 * The files are `MetalanguageInstaller`'s job and the row is this one's. They
 * are separate because everything that can go wrong with a package goes wrong
 * in the first half, and a class that needs a database is a class no unit test
 * here can reach - the suite runs with two constants standing in for Joomla
 * and boots nothing.
 *
 * @since  1.1.0
 */
final class MetalanguageImporter
{
    /**
     * @param  DatabaseInterface  $database  The site's database.
     * @param  string             $siteRoot  Absolute path of the site.
     *
     * @since  1.1.0
     */
    public function __construct(
        private readonly DatabaseInterface $database,
        private readonly string $siteRoot
    ) {
    }

    /**
     * Install one package and record it, replacing that language and version.
     *
     * Replacing rather than refusing: re-importing is how somebody picks up a
     * language they have just changed in Meta-gen, and a version still being
     * worked on gets re-exported a dozen times before it is finished. A
     * version that is finished gets a new number, which is what the number is
     * for.
     *
     * @param  string  $archive  Absolute path of the zip to read.
     *
     * @throws \RuntimeException  When the package is refused, with every reason in the message.
     *
     * @since  1.1.0
     */
    public function import(string $archive): MetalanguageEntry
    {
        $installer = new MetalanguageInstaller($this->siteRoot);
        $entry     = $installer->install($archive);

        $this->record($entry, $installer->manifestJson());

        return $entry;
    }

    /**
     * Put this language in the table, replacing the row for it if there is one.
     *
     * @since  1.1.0
     */
    private function record(MetalanguageEntry $entry, string $manifest): void
    {
        // Bound through locals: bind() takes its value by reference, and a
        // readonly property cannot be passed that way.
        $key     = $entry->key;
        $version = $entry->version;

        $delete = $this->database->getQuery(true)
            ->delete($this->database->quoteName('#__extengen_metalanguages'))
            ->where($this->database->quoteName('lang_key') . ' = :key')
            ->where($this->database->quoteName('version') . ' = :version')
            ->bind(':key', $key)
            ->bind(':version', $version);

        $this->database->setQuery($delete)->execute();

        $row = (object) [
            'lang_key'      => $entry->key,
            'version'       => $entry->version,
            'name'          => $entry->name,
            'root'          => $entry->root,
            'form_root'     => $entry->formRoot,
            'language_file' => $entry->languageFile,
            'manifest'      => $manifest,
            'imported'      => gmdate('Y-m-d H:i:s'),
            'published'     => 1,
        ];

        $this->database->insertObject('#__extengen_metalanguages', $row, 'id');
    }
}
