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

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\Database\DatabaseInterface;
use Yepr\Component\Extengen\Administrator\Metalanguage\MetalanguageCatalogue;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The metalanguage a project is written in: step 3.4.
 *
 * Every language this site has, in the order the catalogue lists them, with
 * ER1 first because that is what an unbound project is written in.
 *
 * **Read-only once it is set**, and that is not caution. The binding decides
 * which forms open the project, so changing it on a project that already has a
 * model in it means the forms on screen no longer describe what is stored -
 * fields that silently post nothing, subforms that render empty, and a save
 * that quietly drops whatever the new forms have no field for. Starting a new
 * project is how you pick a different language.
 *
 * **The class name is the file name, exactly.** Joomla builds it with
 * `ucwords`, which touches letters after whitespace and nothing else, so
 * `type="metalanguage"` looks for `MetalanguageField` - one word, one capital.
 * The family of defect that comes from getting this wrong has turned up four
 * times across these repositories, and it resolves on this filesystem and
 * fails on a Linux server every time.
 *
 * @since  1.1.0
 */
class MetalanguageField extends ListField
{
    /**
     * The field class must know its own type.
     *
     * @var string
     *
     * @since  1.1.0
     */
    protected $type = 'Metalanguage';

    /**
     * Every metalanguage a project could be written in.
     *
     * @return array<int, object>
     *
     * @since  1.1.0
     */
    protected function getOptions(): array
    {
        $options = [];

        foreach ($this->catalogue()->all() as $entry) {
            $options[] = (object) [
                // The built-in posts an empty value rather than `ER1|1.0`, so
                // that a project saved through this field looks exactly like
                // every project made before it existed. One representation of
                // "written in ER1" rather than two.
                'value' => $entry->isBuiltIn() ? '' : $entry->binding(),
                'text'  => $entry->label(),
            ];
        }

        return array_merge($options, parent::getOptions());
    }

    /**
     * A project that has a model in it does not get to change language.
     *
     * @since  1.1.0
     */
    protected function getInput(): string
    {
        if ((int) ($this->form?->getValue('id') ?? 0) > 0) {
            $this->readonly = true;
            $this->disabled = true;
        }

        return parent::getInput();
    }

    /**
     * @since  1.1.0
     */
    private function catalogue(): MetalanguageCatalogue
    {
        return new MetalanguageCatalogue(Factory::getContainer()->get(DatabaseInterface::class));
    }
}
