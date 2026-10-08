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

use Yepr\Component\Extengen\Administrator\Generator\Model\Project;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Where a generated package is: one answer for writing it, downloading it and listing it.
 *
 * `<root>/<project id>-<Component>/<generator id>/<component>-<version>.zip`,
 * beside the unpacked tree. The root is the administrator's `generated/`
 * folder, or a frontend user's own folder (see the site's `Ownership`).
 *
 * The project id is in the folder because the component name is not unique:
 * two projects that both model a component called Conference wrote into one
 * folder and overwrote each other's package, and once the projects list linked
 * to packages, each of them offered the other's as its own.
 *
 * The version is the project's manifest version, so a package generated before
 * the version was raised is not the one this points at - and a list that shows
 * it would offer a download of something that is no longer the project.
 *
 * @since  1.3.4
 */
final class PackageLocation
{
    /**
     * The administrator's output root, relative to the site root.
     *
     * @since  1.3.4
     */
    public const ADMIN_ROOT = 'administrator/components/com_extengen/generated';

    /**
     * The folder one generator's output for one project goes in.
     *
     * @since  1.3.4
     */
    public static function directory(string $root, int $projectId, Project $project, string $generatorId): string
    {
        return rtrim($root, '/\\') . '/' . $projectId . '-' . $project->componentName() . '/' . $generatorId;
    }

    /**
     * The package itself.
     *
     * @since  1.3.4
     */
    public static function archive(string $root, int $projectId, Project $project, string $generatorId): string
    {
        $version = trim((string) ($project->manifest()->version ?? '')) ?: '0.0.0';

        return self::directory($root, $projectId, $project, $generatorId)
            . '/' . strtolower($project->componentName()) . '-' . $version . '.zip';
    }
}
