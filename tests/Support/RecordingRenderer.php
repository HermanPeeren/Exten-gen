<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Support;

use Yepr\Gen\Core\Template\RendererInterface;

/**
 * A renderer that notes every template it is asked for, and delegates.
 *
 * The generators build template names by concatenation, so the only reliable
 * way to know which templates a run actually uses is to watch a run.
 */
final class RecordingRenderer implements RendererInterface
{
    /** @var array<string, int> template path => times asked for */
    private array $seen = [];

    public function __construct(private readonly RendererInterface $inner)
    {
    }

    /**
     * @param  array<string, mixed>  $variables
     */
    public function render(string $template, array $variables = []): string
    {
        // Some generators join a path ending in `/` to a name, and some join a
        // path that does not, so `src//View/` and `src/View/` reach the same
        // file. Twig does not care; a comparison against the filesystem does.
        $normalised = ltrim((string) preg_replace('#/+#', '/', $template), '/');

        $this->seen[$normalised] = ($this->seen[$normalised] ?? 0) + 1;

        return $this->inner->render($template, $variables);
    }

    /**
     * The templates asked for so far, in path order.
     *
     * @return string[]
     */
    public function rendered(): array
    {
        $paths = array_keys($this->seen);

        sort($paths);

        return $paths;
    }
}
