<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Field;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Form\FormField;
use Yepr\Component\Extengen\Administrator\Reference\ReferenceMarkup;

/**
 * A dropdown holding a reference to something else in the project.
 *
 * One class where there were three. `EntityReferenceField`,
 * `FieldReferenceField` and `PageReferenceField` differed only in which part of
 * the model they walked, and each loaded the whole project from the database to
 * do it - so a form with twenty reference fields ran twenty queries for one
 * project. Which type this one offers is an attribute now:
 *
 *     <field name="reference" type="Reference" objecttype="Entity" />
 *
 * Nothing is read here. The choices are decided in the browser by
 * `<extengen-reference>`, from the index the page carries plus whatever the
 * form holds right now, which is what makes an entity added a minute ago
 * available to point at without saving first. Options baked into `<option>`
 * tags at render time can only ever describe the database.
 *
 * This class is the Joomla adapter: it gets a name, an id and a value out of a
 * form. The markup itself, which is the contract with the script, is in
 * `ReferenceMarkup`, where it can be tested without a Joomla to hand.
 *
 * @since  1.0.0
 */
class ReferenceField extends FormField
{
    /**
     * The field type, as the form XML names it.
     *
     * @var string
     *
     * @since  1.0.0
     */
    protected $type = 'Reference';

    /**
     * The markup for one reference dropdown.
     *
     * @since  1.0.0
     */
    protected function getInput(): string
    {
        $markup = new ReferenceMarkup();
        $scope  = (string) ($this->element['scope'] ?? '');

        return $markup->render(
            (string) ($this->element['objecttype'] ?? ''),
            (string) $this->name,
            (string) $this->id,
            (string) $this->value,
            // A scoped dropdown - the fields of one entity - names the sibling
            // field holding its parent. The form XML gives that field's name;
            // only PHP knows the rest of the element id, because Joomla builds
            // it from where the field sits in a repeating group.
            $scope === '' ? null : $markup->siblingId((string) $this->id, (string) $this->fieldname, $scope),
            (string) ($this->element['class'] ?? '')
        );
    }
}
