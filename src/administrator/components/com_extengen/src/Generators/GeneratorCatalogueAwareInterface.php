<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generators;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something that is handed the generator catalogue rather than making one.
 *
 * The component's MVC factory hands it to every model and controller that
 * implements this, the way Joomla's own hands over the database and the form
 * factory. Joomla builds those classes itself, with constructors it decides,
 * so a setter is where injection can happen.
 *
 * @since  1.4.0
 */
interface GeneratorCatalogueAwareInterface
{
    /**
     * @since  1.4.0
     */
    public function setGeneratorCatalogue(GeneratorCatalogue $catalogue): void;
}
