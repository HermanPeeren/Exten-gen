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

use Yepr\Gen\Joomla\Metalanguage\MetalanguageCatalogue;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something that is handed the metalanguage catalogue rather than making one.
 *
 * Handed over by the component's MVC factory to models and controllers, and to
 * the component itself, which is where a form field asks for it - Joomla makes
 * fields with `new`, so a field cannot be injected.
 *
 * @since  1.3.2
 */
interface MetalanguageCatalogueAwareInterface
{
    /**
     * @since  1.3.2
     */
    public function setMetalanguageCatalogue(MetalanguageCatalogue $catalogue): void;
}
