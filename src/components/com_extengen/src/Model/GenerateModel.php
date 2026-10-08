<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\Model;

use Yepr\Component\Extengen\Administrator\Model\GenerateModel as AdministratorGenerateModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Generating on the frontend: step 5.7.
 *
 * The administrator's model as it is. Where the output goes and who may ask
 * are decided by the site's view and controller, which tell this model.
 *
 * @since  1.3.0
 */
class GenerateModel extends AdministratorGenerateModel
{
}
