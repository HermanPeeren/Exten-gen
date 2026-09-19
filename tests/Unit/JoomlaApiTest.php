<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The component's own code calls Joomla 6 APIs, not the ones Joomla 6 keeps
 * around for the sake of code that has not been updated yet.
 *
 * Only one of these was outright broken. `\JHtmlSidebar` is not a class in
 * Joomla 6 at all: it is an alias registered by the `behaviour - compat6`
 * plugin, so on a site that does not run that plugin every list view fatals.
 * The rest still work and emit `E_USER_DEPRECATED`, and are gone in 7.0.
 *
 * A grep over the source is a poor substitute for running the thing, and it is
 * said here rather than implied: nothing in this suite exercises a Joomla
 * request, so nothing here proves the replacements behave. What it does prove
 * is that the old calls are not in the tree, which is the part that creeps back
 * - by copying a method out of a core component, most often, since core itself
 * still calls `Factory::getUser()` in places.
 *
 * `generator_templates/` is deliberately outside the scan. Those files are
 * Twig sources for the code Exten-gen writes, and the same sweep over the
 * generated output is its own step.
 */
final class JoomlaApiTest extends TestCase
{
    /**
     * @return array<string, string[]> label => [needle, what to call instead]
     */
    public static function apisJoomlaSixNoLongerOffers(): array
    {
        return [
            'JHtmlSidebar' => [
                '\JHtmlSidebar',
                'Joomla\CMS\HTML\Helpers\Sidebar, which is the real class behind that alias',
            ],
            'Factory::getUser' => [
                'Factory::getUser',
                '$this->getCurrentUser() in an MVC class, Factory::getApplication()->getIdentity() elsewhere, '
                    . 'or UserFactoryInterface::loadUserById() for somebody else',
            ],
            'Factory::getDbo' => [
                'Factory::getDbo',
                'Factory::getContainer()->get(DatabaseInterface::class), or $this->getDatabase() where there is one',
            ],
            'Factory::getDocument' => [
                'Factory::getDocument',
                'Factory::getApplication()->getDocument()',
            ],
            'CMSObject' => [
                'CMSObject',
                '\stdClass - ArrayHelper::toObject($properties) already builds one',
            ],
            'Table::getProperties' => [
                'getProperties(',
                'get_object_vars($table), which is what Joomla 6 AdminModel::getItem() does',
            ],
            'Table::$_db' => [
                '$this->_db',
                '$this->getDatabase()',
            ],
        ];
    }

    #[DataProvider('apisJoomlaSixNoLongerOffers')]
    public function testTheComponentDoesNotCallIt(string $needle, string $instead): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $relative => $source) {
            if (str_contains($source, $needle)) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            implode(', ', $offenders) . ' still use ' . $needle . '. Call ' . $instead . '.'
        );
    }

    /**
     * The one replacement whose shape can be checked from here: a view asking
     * for the current user asks the trait it already carries.
     */
    public function testTheViewsTakeTheCurrentUserFromTheTraitTheyInherit(): void
    {
        $found = 0;

        foreach ($this->phpFiles() as $relative => $source) {
            if (!str_starts_with($relative, 'View/') || !str_contains($source, 'getCurrentUser()')) {
                continue;
            }

            $found++;

            $this->assertStringContainsString(
                'extends BaseHtmlView',
                $source,
                $relative . ' calls getCurrentUser() but does not extend the view class that provides it.'
            );
        }

        $this->assertGreaterThan(0, $found, 'No view asks for the current user, so this rule is watching nothing.');
    }

    // getError() and setError() are deliberately not in the list above, though
    // they carry the same "removed in 7.0" notice. They are not a call this
    // component makes on its own: `AdminModel::save()` invokes `check()` on a
    // table and then reads `getError()` off it, and every core Joomla 6 table
    // still answers that way. Dropping them means changing a contract with code
    // that is not ours, and no test here can run far enough to see whether it
    // held. It waits for the step that can drive a real site.

    /** @return array<string, string> relative path => source */
    private function phpFiles(): array
    {
        $root  = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/src';
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative         = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        ksort($files);

        return $files;
    }
}
