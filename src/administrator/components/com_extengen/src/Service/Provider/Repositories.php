<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Service\Provider;

use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Yepr\Component\Extengen\Administrator\Repository\ProjectRepository;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Where this component's stored models live, as services.
 *
 * One so far: projects. `ProjectRepository` is the one place that knows how a
 * project is stored - `ModelLayerBoundaryTest` keeps it that way - and this is
 * the one place it is constructed. Shared, and built when first asked for.
 *
 * @since  1.3.2
 */
final class Repositories implements ServiceProviderInterface
{
    /**
     * @since  1.3.2
     */
    public function register(Container $container): void
    {
        $container->share(
            ProjectRepository::class,
            static fn (Container $container): ProjectRepository => new ProjectRepository(
                $container->get(DatabaseInterface::class)
            )
        );
    }
}
