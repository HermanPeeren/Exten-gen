<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Component\Extengen\Administrator\Generators\PackageLocation;

/**
 * Where a generated package is.
 *
 * Written by the generate screen, handed out by the download task and listed
 * in the projects list: three readers of one rule, so it is stated once and
 * checked here. The list only offers what the download task will find, which
 * is why both ask this.
 *
 * @since  1.3.4
 */
final class PackageLocationTest extends TestCase
{
    private function project(string $version): Project
    {
        return Project::fromJson((string) json_encode([
            'name'       => 'Conference',
            'extensions' => ['component' => [
                'component_name' => 'Conference',
                'manifest'       => ['version' => $version],
            ]],
        ]));
    }

    public function testAPackageIsNamedForTheComponentAndItsVersion(): void
    {
        $this->assertSame(
            '/site/generated/3-Conference/joomla6/conference-1.2.0.zip',
            PackageLocation::archive('/site/generated/', 3, $this->project('1.2.0'), 'joomla6')
        );
    }

    public function testEachGeneratorHasAFolderOfItsOwn(): void
    {
        $project = $this->project('1.0.0');

        $this->assertNotSame(
            \dirname(PackageLocation::archive('/r', 1, $project, 'joomla6')),
            \dirname(PackageLocation::archive('/r', 1, $project, 'imported.my-joomla'))
        );
    }

    /**
     * Two projects modelling a component of the same name have a package each.
     *
     * The folder was named for the component alone, so the second overwrote
     * the first's package, and the projects list offered it under both.
     */
    public function testTwoProjectsWithOneComponentNameDoNotShareAPackage(): void
    {
        $project = $this->project('1.0.0');

        $this->assertNotSame(
            PackageLocation::archive('/r', 3, $project, 'joomla6'),
            PackageLocation::archive('/r', 82, $project, 'joomla6')
        );
    }

    public function testAProjectWithNoVersionIsZeroZeroZero(): void
    {
        $this->assertStringEndsWith('/conference-0.0.0.zip', PackageLocation::archive('/r', 1, $this->project(' '), 'joomla6'));
    }
}
