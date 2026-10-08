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
 * The half of `MetalanguageCatalogueAwareInterface` every implementer would write the same.
 *
 * @since  1.4.0
 */
trait MetalanguageCatalogueAwareTrait
{
    /**
     * @var    ?MetalanguageCatalogue
     * @since  1.4.0
     */
    private ?MetalanguageCatalogue $metalanguageCatalogue = null;

    /**
     * @since  1.4.0
     */
    public function setMetalanguageCatalogue(MetalanguageCatalogue $catalogue): void
    {
        $this->metalanguageCatalogue = $catalogue;
    }

    /**
     * The catalogue, which must have been handed over.
     *
     * @throws \UnexpectedValueException  When nothing did: the object was made outside the component's factory.
     *
     * @since  1.4.0
     */
    public function getMetalanguageCatalogue(): MetalanguageCatalogue
    {
        return $this->metalanguageCatalogue ?? throw new \UnexpectedValueException(
            static::class . ' was not given the metalanguage catalogue. Ask the component\'s MVC factory for it.'
        );
    }
}
