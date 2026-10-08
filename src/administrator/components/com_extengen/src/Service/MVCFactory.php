<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Service;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\MVC\Factory\MVCFactory as JoomlaMVCFactory;
use Joomla\Input\Input;
use Psr\Container\ContainerInterface;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogue;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogueAwareInterface;
use Yepr\Component\Extengen\Administrator\Metalanguage\MetalanguageCatalogueAwareInterface;
use Yepr\Component\Extengen\Administrator\Metalanguage\MetalanguageImporterAwareInterface;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepository;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepositoryAwareInterface;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageCatalogue;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageImporter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Joomla's MVC factory, handing this component's own services to what it makes.
 *
 * Joomla's factory builds every controller and model itself, with the
 * constructors Joomla decides, and then hands over the services it knows about
 * through `*AwareInterface` setters - the database, the form factory, the
 * dispatcher. This does the same for the services this component registers in
 * `services/provider.php`: the generator catalogue, the metalanguage catalogue,
 * the importer and the project repository. Nothing else in the component
 * constructs them.
 *
 * The services are taken from the container when an object asks for one, not
 * when the factory is made, so a request that never touches a generator never
 * builds the target registry.
 *
 * It replaces a factory of the same name carried over from the original
 * Extengen, which built everything by autowiring, was never registered, and
 * would have given the models none of the services Joomla's factory gives.
 *
 * @since  1.3.2
 */
class MVCFactory extends JoomlaMVCFactory
{
    /**
     * @param  string              $namespace  The component's namespace.
     * @param  ContainerInterface  $services   The component's container, which holds the services.
     *
     * @since  1.3.2
     */
    public function __construct(string $namespace, private readonly ContainerInterface $services)
    {
        parent::__construct($namespace);
    }

    /**
     * @since  1.3.2
     */
    public function createController($name, $prefix, array $config, CMSApplicationInterface $app, Input $input)
    {
        return $this->handOver(parent::createController($name, $prefix, $config, $app, $input));
    }

    /**
     * @since  1.3.2
     */
    public function createModel($name, $prefix = '', array $config = [])
    {
        return $this->handOver(parent::createModel($name, $prefix, $config));
    }

    /**
     * Give an object the services it says it needs.
     *
     * @template T
     *
     * @param   T  $object  What the parent factory made, or null.
     *
     * @return  T
     *
     * @since   1.3.2
     */
    private function handOver($object)
    {
        if ($object instanceof GeneratorCatalogueAwareInterface) {
            $object->setGeneratorCatalogue($this->services->get(GeneratorCatalogue::class));
        }

        if ($object instanceof MetalanguageCatalogueAwareInterface) {
            $object->setMetalanguageCatalogue($this->services->get(MetalanguageCatalogue::class));
        }

        if ($object instanceof MetalanguageImporterAwareInterface) {
            $object->setMetalanguageImporter($this->services->get(MetalanguageImporter::class));
        }

        if ($object instanceof ProjectRepositoryAwareInterface) {
            $object->setProjectRepository($this->services->get(ProjectRepository::class));
        }

        return $object;
    }
}
