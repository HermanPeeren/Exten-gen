<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The update server tells sites the truth about this release.
 *
 * `updates.xml` repeats what the manifest already says - the element, the
 * version, the platform - beside a URL where that version can be fetched.
 * Repeating it by hand is how a site is offered a version that was never
 * released, or told an extension needs a Joomla it stopped running on.
 *
 * The file this repository shipped did both: it named version 0.8.0 with
 * "No download available" as the URL, targeted Joomla 5.3 and asked for PHP
 * 8.1, while `script.php` refused anything below Joomla 6 and PHP 8.3. It also
 * lived at a URL in the old repository, which nothing here could ever update.
 *
 * So it is generated, and these rules are what make the generation load-bearing
 * rather than a convenience.
 */
final class UpdateServerTest extends TestCase
{
    private function root(): string
    {
        return \dirname(__DIR__, 2);
    }

    private function updates(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/updates.xml');

        $this->assertNotFalse($xml, 'updates.xml is not valid XML.');

        return $xml;
    }

    private function manifest(): \SimpleXMLElement
    {
        $xml = simplexml_load_file($this->root() . '/src/extengen.xml');

        $this->assertNotFalse($xml, 'The manifest is not valid XML.');

        return $xml;
    }

    /**
     * The committed file is the one the script writes.
     *
     * Without this the generator is a suggestion: somebody edits the XML, the
     * two drift, and the drift shows up as a site being offered the wrong
     * thing.
     */
    public function testTheCommittedFileIsWhatTheScriptGenerates(): void
    {
        $committed = (string) file_get_contents($this->root() . '/updates.xml');

        exec('php ' . escapeshellarg($this->root() . '/build/update-xml.php') . ' 2>&1', $output, $status);

        $this->assertSame(0, $status, implode("\n", $output));

        $regenerated = (string) file_get_contents($this->root() . '/updates.xml');

        $this->assertSame(
            $committed,
            $regenerated,
            'updates.xml differs from what build/update-xml.php writes. Run it and commit the result.'
        );
    }

    public function testItOffersTheVersionTheManifestDeclares(): void
    {
        $this->assertSame(
            trim((string) $this->manifest()->version),
            trim((string) $this->updates()->update->version)
        );
    }

    public function testItNamesTheExtensionTheManifestNames(): void
    {
        $this->assertSame(
            trim((string) $this->manifest()->name),
            trim((string) $this->updates()->update->element)
        );
    }

    /**
     * The download URL names this version, in this repository.
     *
     * "No download available" was what the old file said, which is not a URL
     * and tells a site nothing it can act on.
     */
    public function testTheDownloadUrlPointsAtAReleaseOfThisVersion(): void
    {
        $version  = trim((string) $this->manifest()->version);
        $download = trim((string) $this->updates()->update->downloads->downloadurl);

        $this->assertStringStartsWith('https://', $download);
        $this->assertStringContainsString('HermanPeeren/Exten-gen', $download);
        $this->assertStringContainsString($version, $download);
        $this->assertStringEndsWith('.zip', $download);
    }

    /**
     * It asks for the same Joomla and PHP that `script.php` insists on.
     *
     * An update server that offers a package the install script will refuse
     * produces a failed update and no explanation.
     */
    public function testItAgreesWithTheInstallScriptAboutTheEnvironment(): void
    {
        $script = (string) file_get_contents($this->root() . '/src/script.php');

        preg_match("/minimumJoomlaVersion\s*=\s*'([^']+)'/", $script, $joomla);
        preg_match("/minimumPHPVersion\s*=\s*'([^']+)'/", $script, $php);

        $this->assertSame($php[1], trim((string) $this->updates()->update->php_minimum));

        // The platform is a regular expression, so the major version has to be
        // in it rather than compared literally.
        $platform = (string) $this->updates()->update->targetplatform['version'];
        $major    = explode('.', $joomla[1])[0];

        $this->assertStringStartsWith($major, $platform);
        $this->assertMatchesRegularExpression('/^' . $platform . '$/', $joomla[1]);
    }

    /**
     * The manifest points sites at this repository's file, not the old one.
     */
    public function testTheManifestPointsAtThisRepositorysUpdateServer(): void
    {
        $server = trim((string) $this->manifest()->updateservers->server);

        $this->assertStringContainsString('HermanPeeren/Exten-gen', $server);
        $this->assertStringEndsWith('updates.xml', $server);
        $this->assertStringNotContainsString('/Extengen/', $server, 'That is the old repository.');
    }

    /**
     * And the file it points at is the one in this repository.
     */
    public function testTheUrlTheManifestGivesNamesTheFileThatIsHere(): void
    {
        $server = trim((string) $this->manifest()->updateservers->server);

        $this->assertSame('updates.xml', basename($server));
        $this->assertFileExists($this->root() . '/updates.xml');
    }
}
