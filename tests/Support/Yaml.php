<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Support;

use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * A YAML parser, from wherever this checkout has one.
 *
 * The Drupal target generates YAML - an info file, routes, menu links,
 * permissions - and a generated routing file that does not parse is a module
 * whose every page is a 404, with nothing to say why. So the tests read it back
 * rather than pattern-matching it.
 *
 * **Joomla ships `symfony/yaml`**, under `libraries/vendor`, which is the one
 * this repository already has: `/joomla` is git-ignored and every environment
 * that runs the full gates has it, because PHPStan needs a real Joomla to
 * resolve the component's base classes against. So there is nothing to add to
 * `composer.json` and nothing for a consumer to install - the parser is the one
 * an installed Joomla would hand the component at run time.
 *
 * Loading it is not booting the framework, and the distinction matters because
 * the suite's whole value is that it does not: this pulls in an autoloader and
 * asks it for one class. No application, no container, no database. The two
 * defined constants the generators read are still the whole of the bootstrap.
 *
 * @since  1.4.0
 */
final class Yaml
{
    /**
     * Whether a parser could be found.
     *
     * @since  1.4.0
     */
    public static function available(): bool
    {
        if (class_exists(SymfonyYaml::class)) {
            return true;
        }

        $autoload = \dirname(__DIR__, 2) . '/joomla/libraries/vendor/autoload.php';

        if (!is_file($autoload)) {
            return false;
        }

        require_once $autoload;

        return class_exists(SymfonyYaml::class);
    }

    /**
     * Parse, or say why not.
     *
     * @return  mixed  Whatever the document holds.
     *
     * @throws  \RuntimeException  When there is no parser to parse it with.
     *
     * @since   1.4.0
     */
    public static function parse(string $yaml): mixed
    {
        if (!self::available()) {
            throw new \RuntimeException(
                'No YAML parser. Joomla ships one under joomla/libraries/vendor;'
                . ' run composer install-local, or fetch a Joomla into /joomla.'
            );
        }

        return SymfonyYaml::parse($yaml);
    }
}
