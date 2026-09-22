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
 * Every metalanguage this site can write a project in: step 3.4.
 *
 * The imported ones, and ER1, in one list - which is the whole shape the plan
 * asked for: *ER1 is one entry in that list like any other; until 3.5 turns it
 * into a package, the entry is the forms Exten-gen ships*. Everything above
 * this asks the catalogue rather than asking whether a language was imported,
 * so when 3.5 makes ER1 a package the only thing that changes is that
 * `builtIn()` stops being added here.
 *
 * @since  1.1.0
 */
final class MetalanguageCatalogue
{
    /**
     * @param  DatabaseInterface  $database  The site's database.
     *
     * @since  1.1.0
     */
    public function __construct(private readonly DatabaseInterface $database)
    {
    }

    /**
     * Every language a project may be written in, the built-in first.
     *
     * First because it is what an unbound project is written in, so it is the
     * sensible default in a dropdown, and because it is the only one every
     * site has.
     *
     * @return MetalanguageEntry[]
     *
     * @since  1.1.0
     */
    public function all(): array
    {
        $entries = [MetalanguageEntry::builtIn()];

        foreach ($this->rows() as $row) {
            $entries[] = MetalanguageEntry::fromRow($row);
        }

        return $entries;
    }

    /**
     * The language a project says it is written in.
     *
     * Never null: a binding naming a language that is not here any more falls
     * back to the built-in, because the alternative is an edit screen that
     * cannot open at all. Somebody who uninstalled a language out from under a
     * project needs to see the project, not a stack trace - and the forms they
     * get are at least forms.
     *
     * @param  string  $key      What the project stored.
     * @param  string  $version  And which version of it.
     *
     * @since  1.1.0
     */
    public function forProject(string $key, string $version): MetalanguageEntry
    {
        foreach ($this->all() as $entry) {
            if ($entry->answersTo($key, $version)) {
                return $entry;
            }
        }

        return MetalanguageEntry::builtIn();
    }

    /**
     * Whether a binding names something this site actually has.
     *
     * Separate from `forProject()` on purpose: the screen wants to say "the
     * language this project was written in is not installed" and still open,
     * and it cannot do that if the fallback is invisible.
     *
     * @since  1.1.0
     */
    public function knows(string $key, string $version): bool
    {
        foreach ($this->all() as $entry) {
            if ($entry->answersTo($key, $version)) {
                return true;
            }
        }

        return false;
    }

    /**
     * One imported language by key and version, or null.
     *
     * @since  1.1.0
     */
    public function imported(string $key, string $version): ?MetalanguageEntry
    {
        foreach ($this->rows() as $row) {
            $entry = MetalanguageEntry::fromRow($row);

            if ($entry->key === $key && $entry->version === $version) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * The imported rows, newest name order.
     *
     * @return object[]
     *
     * @since  1.1.0
     */
    private function rows(): array
    {
        $query = $this->database->getQuery(true)
            ->select('*')
            ->from($this->database->quoteName('#__extengen_metalanguages'))
            ->where($this->database->quoteName('published') . ' = 1')
            ->order($this->database->quoteName('name') . ' ASC')
            ->order($this->database->quoteName('version') . ' ASC');

        $this->database->setQuery($query);

        return $this->database->loadObjectList() ?: [];
    }
}
