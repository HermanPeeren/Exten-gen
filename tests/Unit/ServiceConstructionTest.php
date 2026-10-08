<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * This component's services are made in one place: its service provider.
 *
 * Until 1.3.2 each consumer made its own - `GeneratorCatalogue::forSite()` in a
 * controller, `Metalanguages::catalogue()` in four models and a form field,
 * `new ProjectRepository()` in two more - so the question "how is the
 * generator catalogue built" had seven answers that happened to agree. They
 * are registered by `Service\Provider\Catalogues` and `Repositories` now,
 * and the component's MVC factory hands them to whatever declares an aware
 * interface for them.
 *
 * A boundary nothing enforces is a boundary that erodes one convenient `new`
 * at a time, so this reads the source. The installer is the one exception,
 * named here, because it runs before the component's container exists.
 *
 * @since  1.3.2
 */
final class ServiceConstructionTest extends TestCase
{
    private const CATALOGUES   = 'administrator/components/com_extengen/src/Service/Provider/Catalogues.php';
    private const REPOSITORIES = 'administrator/components/com_extengen/src/Service/Provider/Repositories.php';

    /**
     * How each service is made, as it would appear in source, and the provider that makes it.
     */
    private const CONSTRUCTIONS = [
        'new GeneratorCatalogue('    => self::CATALOGUES,
        'new MetalanguageCatalogue(' => self::CATALOGUES,
        'new MetalanguageImporter('  => self::CATALOGUES,
        'Targets::registry('         => self::CATALOGUES,
        'new ProjectRepository('     => self::REPOSITORIES,
    ];

    /**
     * Where a construction may stand besides its provider, relative to src/.
     */
    private const ALSO_ALLOWED = [
        // Before the container exists: see the comment beside it.
        'script.php' => ['new MetalanguageImporter('],
    ];

    /**
     * @return array<string, string>  Path relative to src/ => source.
     */
    private function sources(): array
    {
        $root    = \dirname(__DIR__, 2) . '/src';
        $sources = [];
        $tree    = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($tree as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (str_ends_with($path, '.php') && !str_contains($path, '/generator_templates/') && !str_contains($path, '/vendor/')) {
                $sources[substr($path, \strlen($root) + 1)] = (string) file_get_contents($path);
            }
        }

        return $sources;
    }

    public function testTheServicesAreConstructedOnlyByTheProvider(): void
    {
        $found = [];

        foreach ($this->sources() as $path => $source) {
            foreach (self::CONSTRUCTIONS as $construction => $provider) {
                if (
                    $path === $provider
                    || \in_array($construction, self::ALSO_ALLOWED[$path] ?? [], true)
                    || !str_contains($source, $construction)
                ) {
                    continue;
                }

                $found[] = $path . ': ' . $construction;
            }
        }

        $this->assertSame([], $found, 'register it in a provider under Service\\Provider and declare an aware interface instead');
    }

    public function testTheProviderConstructsEachOfThem(): void
    {
        // The other half: an empty provider would pass the test above.
        $sources = $this->sources();

        foreach (self::CONSTRUCTIONS as $construction => $provider) {
            $this->assertStringContainsString($construction, $sources[$provider] ?? '', $provider);
        }
    }

    public function testTheComponentRegistersItsOwnFactoryAndTheCatalogues(): void
    {
        $provider = (string) file_get_contents(
            \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/services/provider.php'
        );

        $this->assertStringContainsString('use Yepr\\Component\\Extengen\\Administrator\\Service\\Provider\\MVCFactory;', $provider);
        $this->assertStringNotContainsString('use Joomla\\CMS\\Extension\\Service\\Provider\\MVCFactory;', $provider);
        $this->assertStringContainsString('new Catalogues()', $provider);
        $this->assertStringContainsString('new Repositories()', $provider);
    }
}
