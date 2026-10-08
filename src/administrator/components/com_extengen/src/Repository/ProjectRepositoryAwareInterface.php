<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Repository;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Something that is handed the project repository rather than making one.
 *
 * Handed over by the component's MVC factory, like the catalogues; see
 * `Service\MVCFactory`.
 *
 * @since  1.3.2
 */
interface ProjectRepositoryAwareInterface
{
    /**
     * @since  1.3.2
     */
    public function setProjectRepository(ProjectRepository $repository): void;
}
