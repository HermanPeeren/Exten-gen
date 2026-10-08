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

use Yepr\Gen\Joomla\Metalanguage\MetalanguageImporter;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something that is handed the metalanguage importer rather than making one.
 *
 * @since  1.4.0
 */
interface MetalanguageImporterAwareInterface
{
    /**
     * @since  1.4.0
     */
    public function setMetalanguageImporter(MetalanguageImporter $importer): void;
}
