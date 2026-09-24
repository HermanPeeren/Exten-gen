<?php

/**
 * @package     Extengen
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerHelper;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;

/**
 * Install script for Exten-gen.
 *
 * Two jobs: refuse an environment the component cannot run in, and make sure
 * the shared library is there.
 *
 * The library carries the generation engine and the packages it needs, and is
 * shared with Gen-gen, Meta-gen and Plug-gen so a site holds one copy rather
 * than one per extension. Joomla has no way for a package manifest to declare a
 * dependency on it, so the package carries a copy and this installs it when the
 * site has none or has an older one. Regular Labs and Akeeba do the same, for
 * the same reason.
 *
 * The check runs on update as well as install: a site can be updated to a
 * version of Exten-gen that needs a newer library than the one already there.
 */
class Com_ExtengenInstallerScript
{
    /**
     * The library this component cannot work without.
     */
    private const LIBRARY = 'yepr_gen';

    /**
     * The oldest library release that has everything this version calls.
     */
    private const LIBRARY_MINIMUM = '0.12.0';

    /**
     * The oldest Joomla this runs on.
     *
     * Six, not four: the generated output targets Joomla 6, and the component's
     * own code uses APIs that older versions do not have.
     *
     * @var string
     */
    private $minimumJoomlaVersion = '6.0';

    /**
     * The oldest PHP this runs on, which is Joomla 6's own minimum.
     *
     * @var string
     */
    private $minimumPHPVersion = '8.3';

    /**
     * Refuse an environment that cannot run this.
     *
     * @param   string            $type    install, update, discover_install or uninstall
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean  False stops the installation.
     */
    public function preflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        if (version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            $this->say(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion), 'error');

            return false;
        }

        if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            $this->say(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion), 'error');

            return false;
        }

        return true;
    }

    /**
     * Put the shared library in place if it is missing or too old.
     *
     * @param   string            $type    install, update, discover_install or uninstall
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean  True, always: a failure here is reported rather than fatal.
     */
    public function postflight($type, $parent): bool
    {
        if ($type === 'uninstall') {
            return true;
        }

        $library = $this->installLibrary($type, $parent);

        // The languages only after the library, because importing one needs
        // `Yepr\Gen\Joomla\Metalanguage` and that arrives with it.
        $this->installLanguages($parent);

        return $library;
    }

    /**
     * Install the metalanguages this component ships.
     *
     * ER1 is one of these now. It used to be twenty-one hand-written form
     * files that the component loaded directly; 3.5 modelled it in LionCore M3
     * and kept the generated set, so it arrives as a package and installs the
     * way an imported one does - through the same reader, with the same
     * refusal if anything in it does not match its own manifest.
     *
     * A component that shipped its own language by copying files into place
     * would be a component whose language is exempt from the checks every
     * other language goes through, which is the kind of exemption that is fine
     * until the day it is not.
     *
     * @param   InstallerAdapter  $parent  The installer running this.
     *
     * @return  void
     */
    private function installLanguages($parent): void
    {
        $directory = $parent->getParent()->getPath('source') . '/packages';
        $packages  = is_dir($directory) ? (glob($directory . '/*.zip') ?: []) : [];

        if ($packages === []) {
            return;
        }

        $database = Factory::getContainer()->get(DatabaseInterface::class);

        foreach ($packages as $package) {
            try {
                $entry = Metalanguages::importer($database, JPATH_ROOT)->import($package);
            } catch (Throwable $e) {
                $this->say(
                    'The ' . basename($package) . ' metalanguage could not be installed: ' . $e->getMessage(),
                    'warning'
                );

                continue;
            }

            $this->bindProjects($database, $entry->key, $entry->version);
        }
    }

    /**
     * Point projects at the version of the shipped language this release carries.
     *
     * Two cases, and they are the same statement made at two different times.
     *
     * *A project that names no language at all.* Every project made before 3.4
     * carries an empty binding, and an empty binding used to mean "the forms
     * this component ships" - which were ER1's. They still are ER1's; they
     * arrive as a package instead. So the binding is filled in rather than left
     * to a fallback, because a fallback is a second answer to "which language"
     * and the whole point of 3.4 was that there is one.
     *
     * *A project bound to an older version of it.* 4.2 is the first time the
     * shipped language has moved: ER1 1.1 adds four optional properties to an
     * edit field, and nothing else. Every model stored under 1.0 is a valid 1.1
     * model - that is what "optional" buys - so the choice is between moving
     * these projects forward and leaving every project that already exists
     * unable to reach the thing the version was bumped for.
     *
     * **This is not a project changing language**, which the edit screen
     * refuses and should: the forms would stop describing what is in the
     * database, and a save would drop whatever the new forms have no field for.
     * A later version of the same language adds fields and removes none, so
     * nothing a project holds becomes unreachable. If a version ever does
     * remove something, this has to become a migration that reads the models
     * rather than one line of SQL - and the release that does it is the one
     * that has to say so.
     *
     * Only this component's own language, by key. A language somebody imported
     * is theirs, and its versions are not this script's business.
     *
     * @param   DatabaseInterface  $database  The site's database.
     * @param   string             $key       The language's key.
     * @param   string             $version   And its version.
     *
     * @return  void
     */
    private function bindProjects($database, string $key, string $version): void
    {
        if ($key !== Metalanguages::SHIPPED) {
            return;
        }

        $query = $database->getQuery(true)
            ->update($database->quoteName('#__extengen_projects'))
            ->set($database->quoteName('metalanguage_key') . ' = :key')
            ->set($database->quoteName('metalanguage_version') . ' = :version')
            ->where(
                '(' . $database->quoteName('metalanguage_key') . " = ''"
                . ' OR (' . $database->quoteName('metalanguage_key') . ' = :existing'
                . ' AND ' . $database->quoteName('metalanguage_version') . ' <> :current))'
            )
            ->bind(':key', $key)
            ->bind(':version', $version)
            ->bind(':existing', $key)
            ->bind(':current', $version);

        try {
            $database->setQuery($query)->execute();
        } catch (Throwable $e) {
            // A fresh install has no projects table yet when this runs on some
            // orderings, and a site with nothing to move is the ordinary case.
            // Neither is worth a warning on screen.
            unset($e);
        }
    }

    /**
     * Install the shared library when the site has none, or an older one.
     *
     * @param   string            $type    The installation route.
     * @param   InstallerAdapter  $parent  The installer running this.
     *
     * @return  bool
     */
    private function installLibrary($type, $parent): bool
    {
        $installed = $this->installedLibraryVersion();

        if ($installed !== null && version_compare($installed, self::LIBRARY_MINIMUM, '>=')) {
            return true;
        }

        $directory = $parent->getParent()->getPath('source') . '/library';
        $archives  = is_dir($directory) ? (glob($directory . '/*.zip') ?: []) : [];

        if ($archives === []) {
            $this->say('The Yepr Gen library is not in this package, so it could not be installed.', 'warning');

            return true;
        }

        // The package carries the library as a zip, and Installer::install()
        // wants a directory with a manifest in it - handed the zip it reports
        // "Can't find XML setup file", which is true and unhelpful. Unpacking
        // first is what Joomla does everywhere it installs from an archive.
        $unpacked = InstallerHelper::unpack((string) $archives[0], true);

        if ($unpacked === false) {
            $this->say('The Yepr Gen library archive could not be unpacked.', 'warning');

            return true;
        }

        $installer = new Installer();
        $installer->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));

        // Not named $installed: that already holds the version this site had,
        // and the message below distinguishes an install from an update by it.
        $success = $installer->install($unpacked['extractdir']);

        // Whether it worked or not, the unpacked copy is temporary.
        InstallerHelper::cleanupInstall((string) $archives[0], $unpacked['extractdir']);

        if ($success) {
            $this->say(
                $installed === null
                    ? 'The Yepr Gen library was installed.'
                    : 'The Yepr Gen library was updated from ' . $installed . '.',
                'message'
            );

            return true;
        }

        // Not fatal. The component is installed; it simply will not generate
        // until the library is there, and saying so is more use than rolling
        // back everything the user just did.
        $this->say('The Yepr Gen library could not be installed. Exten-gen needs it in order to generate.', 'warning');

        return true;
    }

    /**
     * The version of the shared library this site has, or null when it has none.
     *
     * @return  string|null
     */
    private function installedLibraryVersion()
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $element = self::LIBRARY;

        $query = $db->getQuery(true)
            ->select($db->quoteName('manifest_cache'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('library'))
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':element', $element, ParameterType::STRING);

        $db->setQuery($query);

        $manifest = $db->loadResult();

        if (!is_string($manifest) || $manifest === '') {
            return null;
        }

        $decoded = json_decode($manifest, true);

        return is_array($decoded) && isset($decoded['version']) ? (string) $decoded['version'] : null;
    }

    /**
     * Tell the user something, if there is anybody to tell.
     *
     * @param   string  $message  What happened.
     * @param   string  $type     message, warning or error.
     *
     * @return  void
     */
    private function say($message, $type)
    {
        $app = Factory::getApplication();

        if ($app) {
            $app->enqueueMessage($message, $type);
        }
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function install($parent): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function update($parent): bool
    {
        return true;
    }

    /**
     * @param   InstallerAdapter  $parent  The installer
     *
     * @return  boolean
     */
    public function uninstall($parent): bool
    {
        // The library is deliberately left in place. The other extensions in
        // the family share it, and removing something they depend on because
        // this one was uninstalled is how a working site breaks.
        return true;
    }
}
