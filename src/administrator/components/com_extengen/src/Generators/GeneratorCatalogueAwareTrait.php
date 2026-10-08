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
 * The half of `GeneratorCatalogueAwareInterface` every implementer would write the same.
 *
 * @since  1.4.0
 */
trait GeneratorCatalogueAwareTrait
{
    /**
     * @var    ?GeneratorCatalogue
     * @since  1.4.0
     */
    private ?GeneratorCatalogue $generatorCatalogue = null;

    /**
     * @since  1.4.0
     */
    public function setGeneratorCatalogue(GeneratorCatalogue $catalogue): void
    {
        $this->generatorCatalogue = $catalogue;
    }

    /**
     * The catalogue, which must have been handed over.
     *
     * @throws \UnexpectedValueException  When nothing did: the object was made outside the component's factory.
     *
     * @since  1.4.0
     */
    protected function getGeneratorCatalogue(): GeneratorCatalogue
    {
        return $this->generatorCatalogue ?? throw new \UnexpectedValueException(
            static::class . ' was not given the generator catalogue. Ask the component\'s MVC factory for it.'
        );
    }
}
