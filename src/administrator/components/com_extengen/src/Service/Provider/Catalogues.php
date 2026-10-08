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
use Yepr\Component\Extengen\Administrator\Generator\Target\Targets;
use Yepr\Component\Extengen\Administrator\Generators\GeneratorCatalogue;
use Yepr\Component\Extengen\Administrator\Metalanguage\Metalanguages;
use Yepr\Gen\Core\Target\TargetRegistry;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageCatalogue;
use Yepr\Gen\Joomla\Metalanguage\MetalanguageImporter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What this component can generate with and generate from, as services.
 *
 * The one place these are constructed. Each is shared - one per request - and
 * built the first time something asks for it, so a screen that only lists
 * projects never builds the target registry. The component's MVC factory hands
 * them to models and controllers; see `Service\MVCFactory`.
 *
 * The installer is the exception, and has to be: `script.php` imports ER1
 * while Joomla is still installing the component, before this container
 * exists, so it constructs its own importer.
 *
 * @since  1.4.0
 */
final class Catalogues implements ServiceProviderInterface
{
    /**
     * @since  1.4.0
     */
    public function register(Container $container): void
    {
        $component = JPATH_ROOT . '/administrator/components/com_extengen';

        $container->share(
            TargetRegistry::class,
            static fn (): TargetRegistry => Targets::registry(
                $component . '/generator_templates',
                $component . '/compilation_cache'
            )
        );

        $container->share(
            MetalanguageCatalogue::class,
            static fn (Container $container): MetalanguageCatalogue => new MetalanguageCatalogue(
                $container->get(DatabaseInterface::class),
                Metalanguages::TABLE
            )
        );

        $container->share(
            MetalanguageImporter::class,
            static fn (Container $container): MetalanguageImporter => new MetalanguageImporter(
                $container->get(DatabaseInterface::class),
                Metalanguages::TABLE,
                JPATH_ROOT
            )
        );

        $container->share(
            GeneratorCatalogue::class,
            static fn (Container $container): GeneratorCatalogue => new GeneratorCatalogue(
                $container->get(DatabaseInterface::class),
                $container->get(TargetRegistry::class),
                JPATH_ROOT . '/administrator/cache/com_extengen/generators'
            )
        );
    }
}
