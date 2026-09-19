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

use Joomla\CMS\Form\Field\ListField;
use Yepr\Component\Extengen\Administrator\CustomCode\SlotCatalogue;

/**
 * Where a piece of custom code goes.
 *
 * The choices are the slot catalogue, not a list written into the form XML. A
 * second list would be a second place to add a slot and a second place to
 * forget, and a slot the form offers but the generator does not know about
 * stores code that is emitted nowhere.
 *
 *     <field name="slot" type="Slot" owner="Entity" />
 *     <field name="slot" type="Slot" owner="Page" pagetype="indexpage" />
 *
 * Each option carries its explanation as the title, because "extra checks
 * before saving" does not say what is in scope and somebody about to write PHP
 * into a textarea needs to know what `$this` is.
 *
 * @since  1.0.0
 */
class SlotField extends ListField
{
    /**
     * The field type, as the form XML names it.
     *
     * @var string
     *
     * @since  1.0.0
     */
    protected $type = 'Slot';

    /**
     * The slots this kind of object may fill.
     *
     * @return array<int, object>
     *
     * @since  1.0.0
     */
    protected function getOptions(): array
    {
        $owner = (string) ($this->element['owner'] ?? '');

        if ($owner === '') {
            throw new \UnexpectedValueException(
                'A Slot field needs an owner attribute saying which kind of object it belongs to.'
            );
        }

        $pageType = (string) ($this->element['pagetype'] ?? '');
        $options  = [];

        foreach ((new SlotCatalogue())->for($owner, $pageType === '' ? null : $pageType) as $id => $slot) {
            $option = (object) [
                'value'      => $id,
                'text'       => $slot['label'],
                'disable'    => false,
                'class'      => '',
                'selected'   => false,
                'checked'    => false,
                'onclick'    => '',
                'onchange'   => '',
                'title'      => $slot['description'],
            ];

            $options[] = $option;
        }

        return array_merge(parent::getOptions(), $options);
    }
}
