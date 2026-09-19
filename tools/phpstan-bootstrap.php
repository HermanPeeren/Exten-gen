<?php

/**
 * The Joomla constants the component reads.
 *
 * Joomla defines these in `includes/defines.php` when an application boots, so
 * they exist at runtime but not in any file an analyser reads. Declaring them
 * here is the whole of the fix: without it every use is reported, which hides
 * the findings that mean something.
 *
 * Only the ones this component actually reads are here. A bootstrap that
 * defines everything is one nobody can read to answer "what does this
 * depend on".
 *
 * A bootstrap rather than a stub file: PHPStan's stubs describe classes and
 * functions, and a constant has to be defined by something that runs.
 */

declare(strict_types=1);

// The site root, which is where generated output is written.
\define('JPATH_ROOT', '');

// The libraries directory, where the shared Yepr\Gen library is installed.
\define('JPATH_LIBRARIES', '');
