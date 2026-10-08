<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Model;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogue;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The generators this site can run: step 5.4.
 *
 * @since  1.3.0
 */
class GeneratorsModel extends BaseDatabaseModel
{
    /**
     * Every generator, built in first.
     *
     * @return GeneratorEntry[]
     *
     * @since  1.3.0
     */
    public function getItems(): array
    {
        return GeneratorCatalogue::forSite($this->getDatabase())->all();
    }
}
