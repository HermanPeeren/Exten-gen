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
 * The half of `ProjectRepositoryAwareInterface` every implementer would write the same.
 *
 * @since  1.3.2
 */
trait ProjectRepositoryAwareTrait
{
    /**
     * @var    ?ProjectRepository
     * @since  1.3.2
     */
    private ?ProjectRepository $projectRepository = null;

    /**
     * @since  1.3.2
     */
    public function setProjectRepository(ProjectRepository $repository): void
    {
        $this->projectRepository = $repository;
    }

    /**
     * The repository, which must have been handed over.
     *
     * @throws \UnexpectedValueException  When nothing did: the object was made outside the component's factory.
     *
     * @since  1.3.2
     */
    protected function getProjectRepository(): ProjectRepository
    {
        return $this->projectRepository ?? throw new \UnexpectedValueException(
            static::class . ' was not given the project repository. Ask the component\'s MVC factory for it.'
        );
    }
}
