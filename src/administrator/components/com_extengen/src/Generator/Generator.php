<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Yepr\Component\Extengen\Administrator\Generator;

use Yepr\Component\Extengen\Administrator\Generator\Model\Project;
use Yepr\Gen\Core\GeneratorInterface;
use Yepr\Gen\Core\Model\ModelInterface;
use Yepr\Gen\Core\Output\FileCollection;
use Yepr\Gen\Core\Template\RendererInterface;

/**
 * What every concrete generator shares.
 *
 * A generator contributes the files for one concern - the tables, the forms,
 * the back-end MVC - and contributes them to a `FileCollection` held in memory.
 * It does not open a file. That is what lets a whole generation run be asserted
 * in a test without a temp directory, and it means a malformed model cannot
 * write anywhere, because while generating there is no filesystem in play.
 *
 * Before this, the base class wrote each file the moment it was rendered, with
 * `mkdir` and `fopen` and `or die("Unable to open file!")`. Generation was
 * therefore only observable by looking at what had appeared on disk, and a run
 * that failed half way through left half a component behind.
 *
 * **What has not changed, on purpose.** The concrete generators still build
 * their output as strings and still take their variables from the decoded
 * project. Separating the transformation from the templating - the intermediate
 * model the plan is aiming at - is a change to how they are written, and doing
 * it in the same step that moved the I/O would have made the diff unreadable
 * against the golden baseline. What this step buys is that the change is now
 * possible: the seam is a `FileCollection`, not a filesystem.
 *
 * @since  0.9.0
 */
abstract class Generator implements GeneratorInterface
{
    /**
     * The project being generated from.
     *
     * @since  0.9.0
     */
    protected Project $project;

    /**
     * The decoded project, as the concrete generators still read it.
     *
     * @since  0.9.0
     */
    protected object $AST;

    /**
     * Where generated files go.
     *
     * @since  0.9.0
     */
    protected FileCollection $files;

    /**
     * The component name, without the `com_` prefix and with its capitals.
     *
     * @since  0.9.0
     */
    protected string $componentName;

    /**
     * The kind of output, naming both the template set and the generator folder.
     *
     * @since  0.9.0
     */
    protected string $outputType;

    /**
     * What this generator produced, in the words shown to the user.
     *
     * @var string[]
     *
     * @since  0.9.0
     */
    protected array $log = [];

    /**
     * Path to the administrator side of com_extengen.
     *
     * Vestigial. The generators each still compute an absolute output path from
     * it and then never use it, because the file set is in memory now. Removing
     * those lines is a change to seven files that the golden baseline cannot
     * check for me, so it waits for 1.8 where those files are being edited
     * anyway.
     *
     * @since  0.9.0
     */
    protected string $extengenAdminPath;

    /**
     * Paths this generator wrote more than once.
     *
     * @var array<string, int>
     *
     * @since  0.9.0
     */
    protected array $overwritten = [];

    /**
     * Constructor.
     *
     * @param  string               $outputType          For instance "Joomla4".
     * @param  RendererInterface    $renderer            Renders the template set.
     * @param  LanguageStringUtil   $languageStringUtil  Collects language strings while templates render.
     *
     * @since  0.9.0
     */
    public function __construct(
        string $outputType,
        protected readonly RendererInterface $renderer,
        protected readonly LanguageStringUtil $languageStringUtil
    ) {
        $this->outputType = $outputType;
    }

    /**
     * Whether this generator has anything to contribute for the given model.
     *
     * @since  0.9.0
     */
    public function supports(ModelInterface $model): bool
    {
        return $model instanceof Project;
    }

    /**
     * Add this generator's files to the collection.
     *
     * @since  0.9.0
     */
    public function generate(ModelInterface $model, FileCollection $files): void
    {
        if (!$model instanceof Project) {
            throw new \InvalidArgumentException('Exten-gen generators need a Project, got ' . get_debug_type($model) . '.');
        }

        $this->project           = $model;
        $this->AST               = $model->raw();
        $this->files             = $files;
        $this->componentName     = $model->componentName();
        $this->extengenAdminPath = \defined('JPATH_ROOT')
            ? JPATH_ROOT . '/administrator/components/com_extengen/'
            : '';

        // Whichever generator runs first starts the language tree; the rest
        // find it already started. See LanguageStringUtil::useProject().
        $this->languageStringUtil->useProject($this->AST);

        $this->log = $this->generateFiles();
    }

    /**
     * What this generator produced, in the words shown to the user.
     *
     * @return string[]
     *
     * @since  0.9.0
     */
    public function log(): array
    {
        return $this->log;
    }

    /**
     * The generator's own work: render, and add to the collection.
     *
     * @return string[]  A log of what was produced, shown to the user.
     *
     * @since  0.9.0
     */
    abstract protected function generateFiles(): array;

    /**
     * Render a template and add the result to the collection.
     *
     * Keeps the shape the concrete generators already call, so that moving the
     * writing did not mean rewriting twenty call sites at the same time.
     *
     * @param  string               $templateFilePath   Path within the template set, ending with /.
     * @param  string               $templateFileName   The template's file name.
     * @param  string               $generatedFilePath  Path within the package, ending with /.
     * @param  string               $generatedFileName  The generated file's name.
     * @param  array<string, mixed> $templateVariables  Variables for the template.
     *
     * @return string[]  A one-line log.
     *
     * @since  0.9.0
     */
    protected function generateFileWithTemplate(
        string $templateFilePath,
        string $templateFileName,
        string $generatedFilePath,
        string $generatedFileName,
        array $templateVariables = []
    ): array {
        $this->addFile(
            $generatedFilePath . $generatedFileName,
            $this->renderTemplateFragment($templateFilePath, $templateFileName, $templateVariables)
        );

        return [$generatedFileName . ' generated'];
    }

    /**
     * Render a template and hand back the text, for a generator assembling a file itself.
     *
     * @param  string               $templateFilePath   Path within the template set, ending with /.
     * @param  string               $templateFileName   The template's file name.
     * @param  array<string, mixed> $templateVariables  Variables for the template.
     *
     * @since  0.9.0
     */
    protected function renderTemplateFragment(
        string $templateFilePath,
        string $templateFileName,
        array $templateVariables = []
    ): string {
        return $this->renderer->render($templateFilePath . $templateFileName, $templateVariables);
    }

    /**
     * Add a file this generator produced without a template.
     *
     * @since  0.9.0
     */
    protected function addFile(string $path, string $contents): void
    {
        // The collection rejects an absolute or escaping path, so a generator
        // that builds one from model data fails where it was produced rather
        // than somewhere outside the output directory.
        if (!$this->files->has($path)) {
            $this->files->add($path, $contents);

            return;
        }

        // Two pages can still derive the same file name - a details page called
        // Track and an index page called Tracks both give TracksController.php -
        // and `fopen(..., 'w')` used to let the second quietly win. The last
        // write still wins, so that the output does not change in the step that
        // moved the writing, but it is counted now instead of vanishing.
        // KnownBreakageTest pins the ones that exist; 1.5 is where they are fixed.
        $this->overwritten[$path] = ($this->overwritten[$path] ?? 1) + 1;

        $this->files->replace($path, $contents);
    }

    /**
     * Paths this generator wrote more than once, and how often.
     *
     * @return array<string, int>
     *
     * @since  0.9.0
     */
    public function overwrittenFiles(): array
    {
        return $this->overwritten;
    }
}
