<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
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
 * Which tabs a project's edit screen has, read off its language's root form: step 5.2.
 *
 * Extengen had three tabs - Entities, Pages, Extensions - written into the
 * template by field name, which can only ever edit one language. 3.5 made ER1
 * a package and the template fell back to one tab holding everything. Now a
 * language says it: Meta-gen puts features that share a fieldset name into a
 * `<fieldset name="...">`, and this turns each into a tab, in the order the
 * form declares them.
 *
 * The form's unnamed fieldset becomes one tab too, named after the language,
 * but only when it holds something a person sees. Its hidden fields - ER1's
 * `project_id` and every form's `LIonWeb_key` - are rendered outside the tabs,
 * because a tab that holds nothing but hidden inputs is an empty tab.
 *
 * A plain parse of the XML rather than Joomla's `Form`, because by the time a
 * template could ask, the language's fieldsets are merged with the chrome's and
 * nothing says which came from where.
 *
 * @since  1.3.0
 */
final class FormTabs
{
    /**
     * The id of the tab holding the unnamed fieldset's visible fields.
     *
     * @since  1.3.0
     */
    public const UNNAMED = 'model';

    /**
     * @param  array<string, array{label: string, fields: string[]}>  $tabs    By id, in order.
     * @param  string[]                                                 $hidden  Fields rendered outside the tabs.
     *
     * @since  1.3.0
     */
    private function __construct(
        public readonly array $tabs,
        public readonly array $hidden
    ) {
    }

    /**
     * Read a root form.
     *
     * @param  string  $xml            The language's root form.
     * @param  string  $unnamedLabel   What the unnamed fieldset's tab is called: the language's name.
     *
     * @since  1.3.0
     */
    public static function fromXml(string $xml, string $unnamedLabel): self
    {
        $previous = libxml_use_internal_errors(true);
        $form     = simplexml_load_string($xml);

        libxml_use_internal_errors($previous);

        if ($form === false) {
            return new self([], []);
        }

        $tabs    = [];
        $hidden  = [];
        $visible = [];

        foreach ($form->xpath('/form/fieldset') ?: [] as $fieldset) {
            $name   = (string) $fieldset['name'];
            $fields = [];

            // Direct children only. A subform's own fields live in its own
            // file; a `<fields>` group here would be a nested name and is not
            // something a generated root form has.
            foreach ($fieldset->xpath('field') ?: [] as $field) {
                $fieldName = (string) $field['name'];

                if ($fieldName === '') {
                    continue;
                }

                if ((string) $field['type'] === 'hidden') {
                    $hidden[] = $fieldName;

                    continue;
                }

                $fields[] = $fieldName;
            }

            if ($name === '') {
                $visible = array_merge($visible, $fields);

                continue;
            }

            // Two fieldsets of one name are one group, as Joomla treats them.
            $tabs[$name]['label']  = $tabs[$name]['label'] ?? ((string) $fieldset['label'] ?: ucfirst($name));
            $tabs[$name]['fields'] = array_merge($tabs[$name]['fields'] ?? [], $fields);
        }

        if ($visible !== []) {
            $tabs = [self::UNNAMED => ['label' => $unnamedLabel, 'fields' => $visible]] + $tabs;
        }

        return new self($tabs, $hidden);
    }

    /**
     * The tab a field is on, or null when it is on none.
     *
     * @since  1.3.0
     */
    public function tabOf(string $field): ?string
    {
        foreach ($this->tabs as $id => $tab) {
            if (\in_array($field, $tab['fields'], true)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * The tab that opens first.
     *
     * @since  1.3.0
     */
    public function first(): string
    {
        return (string) (array_key_first($this->tabs) ?? self::UNNAMED);
    }
}
