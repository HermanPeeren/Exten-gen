<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every view this component links to is a view it has, spelled the way Joomla
 * will spell it.
 *
 * Joomla does exactly two things with a view name, and they disagree about
 * case:
 *
 *   - `MVCFactory::createView()` asks for `View\ucfirst($name)\HtmlView`.
 *     `ucfirst` touches the first character and nothing else, so everything
 *     after it has to match the directory letter for letter.
 *   - `AbstractView::getName()` returns `strtolower` of the last namespace
 *     segment, and the layout path is `tmpl/<that>`. So the tmpl directory has
 *     to be all lowercase, even when the class directory is not.
 *
 * A name that gets either wrong resolves on a case-insensitive filesystem and
 * fails on a Linux server, which is the third time this family of defect has
 * turned up here: `HtmltypesField` against `HtmlTypesField` under 3.1, and
 * `RuleselectorField` against `RuleSelectorField` in Gen-gen under 2.2. Both
 * were found by CI rather than by anything on this machine.
 *
 * **What it actually found.** Two links to views that are not there at all,
 * which is the same rule catching a worse problem. `src/extengen.xml` opened
 * with `<menu view="extengen">` - the component's own entry in the
 * administrator menu, pointing at a `View\Extengen` that has never existed, so
 * the one link a person clicks to reach this component returned *"View not
 * found [name, type, prefix]: extengen, html, Administrator"*. And the submenu
 * still offered `view=projectforms`, which moved to Meta-gen at 3.0.
 *
 * Neither is a casing defect and neither would have survived a browser spec
 * that followed the manifest's own links - which is what
 * `views-render.cy.js` does now, because it did not before.
 */
final class ViewNamesTest extends TestCase
{
    /**
     * Files that name a view in a link, and are this component's own.
     *
     * `generator_templates/` is left out on purpose: those are Twig templates
     * for a *generated* component, and the views they name are that
     * component's, not this one's.
     *
     * @return string[]
     */
    private function sourceFiles(): array
    {
        $root  = \dirname(__DIR__, 2);
        $files = [];

        foreach (['src', 'cypress', 'tools'] as $directory) {
            $tree = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($tree as $file) {
                $path = str_replace('\\', '/', $file->getPathname());

                if (!$file->isFile() || !\in_array($file->getExtension(), ['php', 'xml', 'js'], true)) {
                    continue;
                }

                if (str_contains($path, '/generator_templates/')) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Every view name this component links to, with where it was written.
     *
     * Only links that are this component's: a `view=` beside an `option=` for
     * somebody else is their business, and `com_fields&view=groups` in the
     * submenu helper is exactly that. A name built from a variable -
     * `view={$view}` in the Cypress helper - is not a literal and cannot be
     * checked here; the specs that pass those values are checked by running.
     *
     * @return array<string, string[]>  view name => files that write it
     */
    private function linkedViews(): array
    {
        $root  = \dirname(__DIR__, 2) . '/';
        $found = [];

        foreach ($this->sourceFiles() as $path) {
            $source = (string) file_get_contents($path);

            // `view=name` in a url, and the `view="name"` attribute the
            // manifest uses for the component's own menu entry. Not
            // `view="project"` inside a form field, which is a componentlayout
            // attribute and names no view class - so the attribute form is
            // only read from the manifest.
            $patterns = ['/[?&](?:amp;)?view=([a-zA-Z_][a-zA-Z0-9_]*)/'];

            if (str_ends_with($path, '/src/extengen.xml')) {
                $patterns[] = '/<menu\s+view="([a-zA-Z_][a-zA-Z0-9_]*)"/';
            }

            foreach ($patterns as $pattern) {
                preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE);

                foreach ($matches[1] as $index => $match) {
                    [$name, $offset] = $match;

                    // Whose component is this link for? The option nearest
                    // before it, if any; a bare `view=` in one of this
                    // component's own files is its own.
                    $before = substr($source, max(0, $offset - 200), min($offset, 200));

                    if (preg_match_all('/option=(?:com_)?([a-zA-Z_]+)/', $before, $options)) {
                        $option = end($options[1]);

                        if ($option !== 'extengen') {
                            continue;
                        }
                    }

                    unset($index);

                    $found[$name][] = str_replace($root, '', $path);
                }
            }
        }

        ksort($found);

        return $found;
    }

    /**
     * What is on disk under `src/View`, read case-sensitively.
     *
     * `scandir` rather than `is_dir`, because `is_dir` answers yes to the
     * wrong case on this filesystem and no on the one this will run on - which
     * is the whole defect this test is about.
     *
     * @return string[]
     */
    private function viewDirectories(): array
    {
        $root = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/src/View';

        return array_values(array_filter(
            scandir($root) ?: [],
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($root . '/' . $entry)
        ));
    }

    public function testEveryLinkedViewResolvesToADirectoryOfThatExactName(): void
    {
        $directories = $this->viewDirectories();
        $unresolved  = [];

        foreach ($this->linkedViews() as $name => $files) {
            if (!\in_array(ucfirst($name), $directories, true)) {
                $unresolved[] = 'view=' . $name . ' wants View/' . ucfirst($name)
                    . ' (' . implode(', ', array_unique($files)) . ')';
            }
        }

        $this->assertSame(
            [],
            $unresolved,
            "These links name a view class directory that is not there:\n  "
            . implode("\n  ", $unresolved)
        );
    }

    /**
     * And a view with a layout directory has it spelled all lowercase.
     *
     * Only the views that have one: six of this component's views echo their
     * markup from `display()` and have no `tmpl/` directory at all, which is
     * different from having one that cannot be found.
     */
    public function testEveryLayoutDirectoryIsTheLowercaseOfItsView(): void
    {
        $root = \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/tmpl';

        $onDisk = array_values(array_filter(
            scandir($root) ?: [],
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($root . '/' . $entry)
        ));

        $wrong = [];

        foreach ($onDisk as $directory) {
            if ($directory !== strtolower($directory)) {
                $wrong[] = 'tmpl/' . $directory . ' should be tmpl/' . strtolower($directory);
            }
        }

        $this->assertSame(
            [],
            $wrong,
            "AbstractView::getName() lowercases, so these are never found on a case-sensitive filesystem:\n  "
            . implode("\n  ", $wrong)
        );

        // And each one belongs to a view that exists, so a renamed view cannot
        // leave its layouts behind as a directory nothing reaches.
        $views = array_map('strtolower', $this->viewDirectories());

        foreach ($onDisk as $directory) {
            $this->assertContains(
                $directory,
                $views,
                'tmpl/' . $directory . ' has no view; nothing will ever render it.'
            );
        }
    }

    /**
     * The manifest's menu entries point at views this component has.
     *
     * Read separately from the rule above because these are the links a person
     * actually clicks: Joomla builds the administrator menu from them, and a
     * broken one is the component being unreachable rather than one screen
     * being wrong.
     */
    public function testTheAdministratorMenuPointsAtViewsThatExist(): void
    {
        $manifest = simplexml_load_file(\dirname(__DIR__, 2) . '/src/extengen.xml');

        $this->assertNotFalse($manifest);

        $directories = $this->viewDirectories();
        $entries     = [];

        foreach ($manifest->xpath('//administration/menu') ?: [] as $menu) {
            $entries[] = (string) $menu['view'];
        }

        foreach ($manifest->xpath('//administration/submenu/menu') ?: [] as $menu) {
            if (preg_match('/view=([a-zA-Z_][a-zA-Z0-9_]*)/', (string) $menu['link'], $match)) {
                $entries[] = $match[1];
            }
        }

        $entries = array_values(array_filter($entries));

        $this->assertNotEmpty($entries, 'The manifest declares no menu at all, which cannot be right.');

        foreach ($entries as $name) {
            $this->assertContains(
                ucfirst($name),
                $directories,
                'The administrator menu offers view=' . $name . ', and View/' . ucfirst($name) . ' is not there.'
            );
        }
    }
}
