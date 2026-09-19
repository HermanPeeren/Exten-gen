<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Loading a stored model happens in one place.
 *
 * There were thirteen. Five field classes and four MVC models each held the same
 * five-line query and a `json_decode`, and two of the thirteen had drifted to a
 * different method name for it — which is how a duplicated fragment announces
 * that nobody can see all of its copies at once.
 *
 * The cost of that is not the typing. It is that a question like "what format is
 * this model stored in" has nowhere to be asked, and a fix has thirteen places
 * to be applied and twelve places to be forgotten.
 */
final class ModelLayerBoundaryTest extends TestCase
{
    private const ALLOWED = [
        'Repository/ProjectRepository.php',
        'Repository/ProjectFormRepository.php',
        'Generator/Model/Project.php',
        // The Joomla form round trip: it decodes what it is about to hand the
        // edit form, and encodes what the user typed back. ProjectModel goes
        // through Project; ProjectFormModel cannot yet, because the meta-model
        // it stores has no type of its own until Meta-gen exists.
        'Model/ProjectModel.php',
        'Model/ProjectFormModel.php',
    ];

    private function sourceRoot(): string
    {
        return \dirname(__DIR__, 2) . '/src/administrator/components/com_extengen/src';
    }

    public function testNothingElseQueriesTheFormDataColumn(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $relative => $source) {
            if (\in_array($relative, self::ALLOWED, true)) {
                continue;
            }

            if (str_contains($source, "quoteName('form_data')") || str_contains($source, '->form_data')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'These read form_data directly: ' . implode(', ', $offenders));
    }

    // There is deliberately no test that nothing else names the storage tables.
    // Three classes do - the modal project picker, the associations helper and
    // the administrator HTML service - and all three are selecting a name or an
    // id for a picker or a language association. That is an ordinary query
    // against a table, not a second copy of "how a model is loaded", and a rule
    // forbidding it would be a rule nobody believes.

    /**
     * The reference fields no longer carry a query, so they no longer carry the
     * import a query needs. Left behind, it is a hint that the code still does
     * something it stopped doing.
     */
    public function testTheReferenceFieldsNoLongerImportTheQueryTypes(): void
    {
        $offenders = [];

        foreach ($this->phpFiles() as $relative => $source) {
            if (!str_starts_with($relative, 'Field/')) {
                continue;
            }

            if (str_contains($source, 'use Joomla\Database\ParameterType;')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'These still import ParameterType: ' . implode(', ', $offenders));
    }

    /** @return array<string, string> relative path => source */
    private function phpFiles(): array
    {
        $root  = $this->sourceRoot();
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative         = str_replace('\\', '/', substr($file->getPathname(), \strlen($root) + 1));
            $files[$relative] = (string) file_get_contents($file->getPathname());
        }

        ksort($files);

        return $files;
    }
}
