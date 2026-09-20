<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The forms describe the models, and nothing compiles them.
 *
 * Every defect 3.1 repaired was invisible until somebody opened a screen, and
 * two of them were invisible then too:
 *
 *   - `classifier.xml` offered "Annotation" and pointed at an `annotation.xml`
 *     that was never written. Joomla does not report a `formsource` it cannot
 *     resolve; the subform renders with no fields in it, which looks like a
 *     feature nobody had filled in yet.
 *   - `editfield.xml` asked for `type="htmltypes"`, and the class is
 *     `HtmlTypesField`. Joomla builds the class name with `ucwords`, which only
 *     touches letters after whitespace, so it looked for `HtmltypesField`. On
 *     Windows the filesystem does not care; on a Linux server the class is not
 *     found and the closed list of HTML types quietly becomes a text box.
 *   - `concept.xml` named a field type that lived under a different prefix than
 *     the one its fieldset declared.
 *   - `interface.xml` pointed at a `classifier_property.xml` that never
 *     existed, and at an ER1 form from inside the meta-model. Nothing reached
 *     it at all, which is why nobody had noticed.
 *
 * This is the compiler those files do not have.
 */
final class FormsTest extends TestCase
{
    /**
     * Field types Joomla itself provides.
     *
     * @var string[]
     */
    private const JOOMLA_TYPES = [
        'text', 'textarea', 'number', 'hidden', 'list', 'subform', 'radio', 'checkbox', 'checkboxes',
        'calendar', 'accesslevel', 'categoryedit', 'ordering', 'contentlanguage', 'editor', 'note',
        'spacer', 'url', 'email', 'integer', 'file', 'media', 'tag', 'groupedlist', 'status',
        'limitbox', 'rules', 'color', 'password', 'range', 'repeatable', 'combo', 'componentlayout',
    ];

    private function root(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/';
    }

    /**
     * Every form the component ships.
     *
     * @return array<string, \SimpleXMLElement>  Relative path => parsed form.
     */
    private function forms(): array
    {
        $forms = [];
        $base  = $this->root() . 'forms';

        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($tree as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'xml') {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), \strlen($base) + 1));
            $xml      = simplexml_load_file($file->getPathname());

            $this->assertNotFalse($xml, $relative . ' is not valid XML.');

            $forms[$relative] = $xml;
        }

        return $forms;
    }

    public function testEveryFormParses(): void
    {
        $this->assertNotEmpty($this->forms());
    }

    /**
     * Every subform points at a form that is there.
     */
    public function testEverySubformSourceExists(): void
    {
        $prefix  = 'administrator/components/com_extengen/';
        $missing = [];

        foreach ($this->forms() as $name => $form) {
            foreach ($form->xpath('//field[@formsource]') ?: [] as $field) {
                $source = (string) $field['formsource'];
                $path   = str_starts_with($source, $prefix)
                    ? $this->root() . substr($source, \strlen($prefix))
                    : $this->root() . $source;

                if (!is_file($path)) {
                    $missing[] = $name . ' -> ' . $source;
                }
            }
        }

        $this->assertSame([], $missing, "Subforms pointing at nothing:\n  " . implode("\n  ", $missing));
    }

    /**
     * Every custom field type resolves, the way Joomla resolves it.
     *
     * `FormHelper::loadClass()` builds the class name as
     * `ucfirst(ucwords($type)) . 'Field'` under the prefixes the form
     * registered. Compared against a directory listing, which is
     * case-sensitive on every platform - unlike `is_file` on this one.
     */
    public function testEveryCustomFieldTypeResolves(): void
    {
        $unresolved = [];

        foreach ($this->forms() as $name => $form) {
            foreach ($form->xpath('//field[@type]') ?: [] as $field) {
                $type = (string) $field['type'];

                if (\in_array(strtolower($type), self::JOOMLA_TYPES, true)) {
                    continue;
                }

                $prefix    = $this->prefixFor($field);
                $directory = $this->directoryFor($prefix);

                // A type under somebody else's prefix - Joomla's own category
                // field, for instance - is their problem, not this one's.
                if ($directory === null) {
                    continue;
                }

                $class = ucfirst(ucwords($type)) . 'Field.php';

                if (!is_dir($directory) || !\in_array($class, scandir($directory) ?: [], true)) {
                    $unresolved[] = $name . ': type="' . $type . '" wants ' . $prefix . '\\' . basename($class, '.php');
                }
            }
        }

        $this->assertSame([], $unresolved, "Field types that resolve to nothing:\n  " . implode("\n  ", $unresolved));
    }

    /**
     * Every field class declares the type its form asks for.
     */
    public function testEveryFieldClassDeclaresItsOwnType(): void
    {
        $tree = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root() . 'src/Field', \FilesystemIterator::SKIP_DOTS)
        );

        $checked = 0;

        foreach ($tree as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), 'Field.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $class  = basename($file->getFilename(), 'Field.php');

            // A field in a subdirectory of Field/ is named for its path:
            // `Modal/ProjectField` is `type="modal_project"`, because Joomla
            // turns an underscore into a namespace step before looking. So the
            // expected `$type` is the path, not just the class.
            $relative = str_replace(
                '\\',
                '/',
                substr(\dirname($file->getPathname()), \strlen($this->root() . 'src/Field'))
            );

            $expected = trim(str_replace('/', '_', $relative), '_');
            $expected = $expected === '' ? $class : $expected . '_' . $class;

            $this->assertStringContainsString(
                "\$type = '" . $expected . "'",
                $source,
                $file->getFilename() . ' does not declare $type = ' . $expected
            );

            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    /**
     * Where the classes under a field prefix live, or null for somebody else's.
     *
     * Two prefixes are ours. `Reference` moved into the shared library when
     * Meta-gen was split out, because three components edit models with
     * reference dropdowns and this one was carrying the mechanism for all
     * three - so the forms that use it name `Yepr\Gen\Joomla\Form\Field` and the
     * class is in `vendor/`. Resolving it there rather than skipping it
     * matters: a field type Joomla cannot resolve falls back to a plain text
     * box, silently turning a closed list into a place to type anything at
     * all, and moving the class out of this repository must not move that
     * check out with it.
     */
    private function directoryFor(string $prefix): ?string
    {
        $library = 'Yepr\\Gen\\Joomla\\Form\\Field';

        if ($prefix === $library) {
            return \dirname(__DIR__, 2) . '/vendor/yepr/generator-core/src/Joomla/Form/Field';
        }

        $own = 'Yepr\\Component\\Extengen\\Administrator\\Field';

        if (str_starts_with($prefix, $own)) {
            return $this->root() . 'src/Field' . str_replace('\\', '/', substr($prefix, \strlen($own)));
        }

        return null;
    }

    /**
     * Which field prefix applies to a field: its own, or its fieldset's.
     */
    private function prefixFor(\SimpleXMLElement $field): string
    {
        if (isset($field['addfieldprefix'])) {
            return (string) $field['addfieldprefix'];
        }

        $fieldset = $field->xpath('ancestor::*[@addfieldprefix]');

        return $fieldset === [] || $fieldset === false ? '' : (string) $fieldset[0]['addfieldprefix'];
    }
}
