<?php

/**
 * @package     Extengen
 * @subpackage  Extengen component, site
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Site\View\Generate;

use Yepr\Component\Extengen\Administrator\View\Generate\HtmlView as AdministratorHtmlView;
use Yepr\Component\Extengen\Site\Helper\Ownership;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Generating on the frontend: step 5.7.
 *
 * The administrator's chooser and result, with three differences. The project
 * must be the visitor's. The output goes into the visitor's own folder. And
 * the result says nothing about where on the server it went, and never offers
 * to install anything.
 *
 * @since  1.3.0
 */
class HtmlView extends AdministratorHtmlView
{
    /**
     * @param   \Yepr\Component\Extengen\Administrator\Model\GenerateModel  $model  The model.
     *
     * @return  void
     *
     * @since   1.3.0
     */
    protected function prepareModel($model): void
    {
        $user = $this->getCurrentUser();

        Ownership::assertMayOpen($this->projectId, $user);

        $model->setOutputRoot(Ownership::outputRoot($user));
    }

    /**
     * The log without the lines that name a path on the server.
     *
     * @param   string[]  $log  What the run wrote.
     *
     * @return  string[]
     *
     * @since   1.3.0
     */
    protected function logForDisplay(array $log): array
    {
        return array_values(array_filter(
            $log,
            static fn (string $line): bool => !str_starts_with($line, 'package: ') && !str_starts_with($line, 'unpacked: ')
        ));
    }

    /**
     * Never on the frontend, whatever the component's options say.
     *
     * @return  bool
     *
     * @since   1.3.0
     */
    protected function mayInstall(): bool
    {
        return false;
    }
}
