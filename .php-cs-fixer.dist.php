<?php

/**
 * php-cs-fixer configuration.
 *
 * PSR-12 plus a few rules this codebase follows. Run it with `composer cs-fix`;
 * it rewrites files, so read the diff.
 *
 * Scoped to the new code for now. The component's own tree joins at step 1.11,
 * together with the Joomla 6 sweep: reformatting eighteen thousand lines in one
 * commit would bury every real change made alongside it.
 *
 * declare_strict_types is deliberately not a rule here. php-cs-fixer classes it
 * as risky, because adding it can change how a file behaves, so it is a thing
 * someone decides per file rather than a thing a tool does on the way past.
 */

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/tests', __DIR__ . '/build'])
    // Generated output, compared byte for byte by the golden tests.
    // Reformatting it would break the very thing it pins.
    ->exclude(['Fixtures/golden/expected'])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12'                      => true,
        'array_syntax'                => ['syntax' => 'short'],
        'binary_operator_spaces'      => [
            'default'   => 'single_space',
            'operators' => ['=>' => 'align_single_space_minimal', '=' => 'align_single_space_minimal'],
        ],
        'no_unused_imports'           => true,
        'ordered_imports'             => ['sort_algorithm' => 'alpha'],
        'single_quote'                => true,
        'trailing_comma_in_multiline' => true,
        'no_trailing_whitespace'      => true,
        'single_line_empty_body'      => false,
    ])
    ->setFinder($finder);
