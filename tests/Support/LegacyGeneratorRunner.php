<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Support;

use Yepr\Component\Extengen\Administrator\Model\Generator\Joomla4\AdminEntities;
use Yepr\Component\Extengen\Administrator\Model\Generator\Joomla4\AdminGeneral;
use Yepr\Component\Extengen\Administrator\Model\Generator\Joomla4\AdminMVC;
use Yepr\Component\Extengen\Administrator\Model\Generator\Joomla4\ComponentGeneral;
use Yepr\Component\Extengen\Administrator\Model\Generator\Joomla4\Forms;
use Yepr\Component\Extengen\Administrator\Model\Generator\Joomla4\SiteMVC;
use Yepr\Component\Extengen\Administrator\Model\LanguageStringUtil;
use Yepr\Gen\Core\Output\FileCollection;

/**
 * Runs today's generators and collects what they wrote.
 *
 * Scaffolding, on purpose and not for long. Step 1.4 moves generation onto the
 * shared Pipeline, where a generator writes into a FileCollection and never
 * touches a filesystem; this exists only so the output *before* that move can be
 * pinned, and it goes when the move is done.
 *
 * Two things it has to be faithful about, because the golden files are worthless
 * otherwise:
 *
 * - **The order and the set of generators** are transcribed from
 *   `GenerateModel::generate()`, which is what the component runs when somebody
 *   presses the button. Not reused directly: that class extends Joomla's
 *   AdminModel and reads the model from the database, so loading it would mean
 *   bootstrapping the CMS to test a transformation that turns out not to need
 *   it.
 * - **The language files** are written by `GenerateModel` itself rather than by
 *   any generator, so that loop is transcribed too. Leaving it out would have
 *   silently dropped every .ini file from the baseline.
 *
 * Fidelity is checked rather than asserted: `LegacyOutputFidelityTest` compares
 * what this produces against output the real component generated through its own
 * interface, which is committed in the predecessor repository.
 *
 * The generators need no CMS. They reference JPATH_ROOT and Twig and nothing
 * else - the two Joomla imports in Generator.php are unused - which is why this
 * can run at all, and a good sign for 1.4.
 */
final class LegacyGeneratorRunner
{
    /**
     * The generators, in the order GenerateModel runs them.
     *
     * @var string[]
     */
    private const GENERATORS = [
        ComponentGeneral::class,
        AdminGeneral::class,
        AdminEntities::class,
        AdminMVC::class,
        Forms::class,
        SiteMVC::class,
    ];

    private const OUTPUT_TYPE = 'Joomla4';

    /** Where the scratch Joomla root was put, once per process. */
    private static ?string $root = null;

    /**
     * Generate for one model and return what landed on disk.
     *
     * @param  object  $ast  The decoded project, as the component stores it.
     */
    public static function run(object $ast): FileCollection
    {
        self::bootstrap();

        $componentName = (string) $ast->extensions->component->component_name;
        $outputRoot    = self::componentOutputDirectory($componentName);

        // Start from nothing, so a file this run does not produce cannot be
        // picked up from a previous one.
        self::removeDirectory($outputRoot);

        $languageStringUtil = new LanguageStringUtil($ast);

        foreach (self::GENERATORS as $generator) {
            (new $generator(self::OUTPUT_TYPE, $ast, $languageStringUtil))->generate();
        }

        self::writeLanguageFiles($ast, $languageStringUtil, $componentName);

        return self::collect($outputRoot);
    }

    /**
     * Put a Joomla-shaped root in a temp directory and point the constants at it.
     *
     * The generators build every path from JPATH_ROOT, so this is enough to run
     * them - and it keeps generation out of the working copy and off the
     * development site, which is where they would otherwise write.
     */
    private static function bootstrap(): void
    {
        if (self::$root !== null) {
            return;
        }

        $repository = \dirname(__DIR__, 2);
        $component  = $repository . '/src/administrator/components/com_extengen';

        $root  = sys_get_temp_dir() . '/extengen-golden-' . getmypid();
        $admin = $root . '/administrator/components/com_extengen';

        if (!is_dir($admin) && !mkdir($admin, 0755, true) && !is_dir($admin)) {
            throw new \RuntimeException('Cannot create the scratch root at ' . $root);
        }

        // Only what the generators read: the templates, and somewhere for Twig
        // to compile into.
        self::copyDirectory($component . '/generator_templates', $admin . '/generator_templates');

        foreach ([$admin . '/compilation_cache', $admin . '/generated'] as $directory) {
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new \RuntimeException('Cannot create ' . $directory);
            }
        }

        if (!\defined('JPATH_ROOT')) {
            \define('JPATH_ROOT', $root);
        }

        if (!\defined('JPATH_LIBRARIES')) {
            // Twig comes from the shared library rather than the old
            // libraries/yepr tree, because composer has already put it there and
            // the generator only asks for an autoloader.
            \define('JPATH_LIBRARIES', $repository . '/vendor/yepr');
        }

        // Generator.php requires JPATH_LIBRARIES . '/yepr/vendor/autoload.php'.
        // Satisfy it with this repository's autoloader, which has Twig in it.
        $shim = $repository . '/vendor/yepr/yepr/vendor';

        if (!is_dir($shim) && !mkdir($shim, 0755, true) && !is_dir($shim)) {
            throw new \RuntimeException('Cannot create the Twig autoload shim at ' . $shim);
        }

        file_put_contents(
            $shim . '/autoload.php',
            "<?php\n\n// Written by the test harness: the legacy generator requires an autoloader\n"
            . "// at this path. Twig arrives through the repository's own vendor tree.\n"
            . 'require_once ' . var_export($repository . '/vendor/autoload.php', true) . ";\n"
        );

        self::$root = $root;
    }

    /** Where one component's output lands, matching Generator's own path building. */
    private static function componentOutputDirectory(string $componentName): string
    {
        return self::$root . '/administrator/components/com_extengen/generated/'
            . $componentName . '/' . self::OUTPUT_TYPE . '/com_' . strtolower($componentName);
    }

    /**
     * Write the language files.
     *
     * Transcribed from GenerateModel::generate(), which does this itself after
     * the generators have run and filled the language tree.
     */
    private static function writeLanguageFiles(
        object $ast,
        LanguageStringUtil $languageStringUtil,
        string $componentName
    ): void {
        $root = self::componentOutputDirectory($componentName);

        foreach ($languageStringUtil->getLangTree() as $sectionName => $section) {
            $path = match ($sectionName) {
                'frontend' => 'components/com_' . strtolower($componentName) . '/language/',
                default    => 'administrator/components/com_' . strtolower($componentName) . '/language/',
            };

            foreach ($section->languages as $language) {
                $directory = $root . '/' . $path . $language->language_code . '-' . $language->country_code;

                if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                    throw new \RuntimeException('Cannot create ' . $directory);
                }

                $lines = [];

                foreach ($language->key_value_pairs as $pair) {
                    $lines[] = $pair->language_string . '="' . $pair->locale_string . '"';
                }

                sort($lines);

                $suffix = $sectionName === 'sys' ? '.sys' : '';

                file_put_contents(
                    $directory . '/com_' . strtolower($componentName) . $suffix . '.ini',
                    implode("\n", $lines)
                );
            }
        }
    }

    /** Read a generated tree into a file collection. */
    private static function collect(string $root): FileCollection
    {
        $files = new FileCollection();

        if (!is_dir($root)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));

            $files->add($relative, (string) file_get_contents($file->getPathname()));
        }

        return $files;
    }

    private static function copyDirectory(string $from, string $to): void
    {
        if (!is_dir($to) && !mkdir($to, 0755, true) && !is_dir($to)) {
            throw new \RuntimeException('Cannot create ' . $to);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target = $to . '/' . substr($item->getPathname(), \strlen($from) + 1);

            if ($item->isDir()) {
                if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                    throw new \RuntimeException('Cannot create ' . $target);
                }

                continue;
            }

            copy($item->getPathname(), $target);
        }
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
