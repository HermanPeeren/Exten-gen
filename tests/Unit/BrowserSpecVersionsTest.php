<?php

declare(strict_types=1);

namespace Yepr\Component\Extengen\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * No browser spec names the version of ER1 it expects.
 *
 * Twice a spec named it - 'ER1 1.1', then 'ER1|1.2' - after ER1 had moved on.
 * Both went on passing here, where the development site still had the old
 * version's row, and failed in CI, where a fresh site has only the version the
 * checkout ships. `cy.shippedEr1Version()` reads it off the package instead.
 *
 * @since  1.3.5
 */
final class BrowserSpecVersionsTest extends TestCase
{
    public function testNoSpecWritesDownAnEr1Version(): void
    {
        $specs = glob(\dirname(__DIR__) . '/cypress/e2e/*.cy.js') ?: [];
        $found = [];

        $this->assertNotEmpty($specs, 'the specs are where this looks');

        foreach ($specs as $spec) {
            foreach ((array) file($spec) as $number => $line) {
                // In a string, not in prose: a comment may say what happened.
                if (preg_match('/[\'"`][^\'"`]*ER1[ |]\d+\.\d+/', (string) $line) && !preg_match('/^\s*(\/\/|\*)/', (string) $line)) {
                    $found[] = basename($spec) . ':' . ($number + 1) . ': ' . trim((string) $line);
                }
            }
        }

        $this->assertSame([], $found, 'use cy.shippedEr1Version() rather than a version written into the spec');
    }
}
