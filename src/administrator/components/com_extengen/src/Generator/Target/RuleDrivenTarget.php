<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Target;

use Yepr\Gen\Core\Rule\Vocabulary;
use Yepr\Gen\Core\Target\TargetInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * A target whose files come from a rule set, so a generator modelled in Gen-gen can run on it: step 5.4.
 *
 * Joomla 6 is the only one so far. The WordPress and Drupal targets are
 * hand-written emitters with no rules, so there is nothing for an imported
 * generator to replace there, and the import refuses a package for them.
 *
 * @since  1.3.0
 */
interface RuleDrivenTarget extends TargetInterface
{
    /**
     * What a rule for this target may name.
     *
     * @since  1.3.0
     */
    public function vocabulary(): Vocabulary;

    /**
     * The rule prefixes this target's generators claim.
     *
     * @return string[]
     *
     * @since  1.3.0
     */
    public function rulePrefixes(): array;

    /**
     * The same target, running another rule file.
     *
     * @since  1.3.0
     */
    public function withRules(string $ruleFile): self;
}
