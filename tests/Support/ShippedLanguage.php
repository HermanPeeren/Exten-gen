<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Support;

use Yepr\Gen\Core\Package\PackageReader;

/**
 * The forms this component ships, wherever they happen to live.
 *
 * Until 3.5 that was `src/administrator/components/com_extengen/forms`, and the
 * rules about them - every reference points at a type the index carries, every
 * slot names an owner the catalogue knows - read that directory. ER1 is a
 * generated metalanguage now and ships as `packages/ER1-<version>.zip`, so the rules
 * read the package. What they assert has not changed; where the forms are has.
 *
 * **Read through `PackageReader` rather than unzipped by hand**, so that a
 * damaged package fails these tests for the same reason it would fail an
 * install - rather than quietly yielding no forms and letting every rule pass
 * by checking nothing. `SlotContractTest` is the reason that matters: its
 * vacuity guard is what caught the generated set losing the Slot picker, and a
 * helper that shrugged at an unreadable package would have taken the guard's
 * teeth out.
 *
 * @since  1.4.0
 */
final class ShippedLanguage
{
    /**
     * Every form in the shipped package, keyed by its path inside it.
     *
     * @return array<string, \SimpleXMLElement>
     *
     * @throws \RuntimeException  When the package is missing, damaged, or holds no forms.
     *
     * @since  1.4.0
     */
    public static function forms(): array
    {
        $package  = self::package();
        $reader   = PackageReader::fromZip($package);
        $problems = $reader->problems();

        if ($problems !== []) {
            throw new \RuntimeException(
                'The shipped language does not describe itself correctly: ' . implode(' ', $problems)
            );
        }

        $forms = [];

        foreach ($reader->forms() as $path => $contents) {
            $xml = simplexml_load_string($contents);

            if ($xml === false) {
                throw new \RuntimeException($path . ' in ' . basename($package) . ' is not valid XML.');
            }

            $forms[$path] = $xml;
        }

        if ($forms === []) {
            throw new \RuntimeException(basename($package) . ' holds no forms.');
        }

        ksort($forms);

        return $forms;
    }

    /**
     * Where the shipped package is.
     *
     * @throws \RuntimeException  When there is not exactly one.
     *
     * @since  1.4.0
     */
    public static function package(): string
    {
        $found = glob(\dirname(__DIR__, 2) . '/packages/*.zip') ?: [];

        if (\count($found) !== 1) {
            throw new \RuntimeException(
                'Expected one shipped language package, found ' . \count($found)
                . '. These rules read "the" shipped language and would have to say which.'
            );
        }

        return $found[0];
    }
}
