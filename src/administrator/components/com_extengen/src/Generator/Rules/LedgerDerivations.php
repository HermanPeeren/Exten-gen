<?php

/**
 * @package     Extengen
 * @subpackage  Generator
 *
 * @copyright   Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Yepr\Component\Extengen\Administrator\Generator\Rules;

/**
 * What the LedgerLang generator computes: account roles and posting rules as PHP.
 *
 * LedgerLang derives from ER1 and adds `account_roles` and `posting_rules` to
 * the project. ER1's rules never read either, so an ER1 project never reaches
 * these. Only the rules the LedgerLang generator adds to the Joomla 6 one bind
 * them (`ledgerlang.rules.json`). `Joomla6Derivations` registers them under the
 * names `ledgerAccountRoles` and `ledgerPostingRules`.
 *
 * A posting line's amount is an expression over the event's data, such as
 * `amount - vat`. It becomes PHP here, at generation time, as calls to the
 * generated class's exact arithmetic (6.9), so the generated class needs no
 * interpreter. That is also why it is parsed rather
 * than pasted: only names, numbers, `+ - * /` and brackets get through, in an
 * order that makes an arithmetic expression. Anything else becomes a line that
 * throws when it is posted, plus a warning in the generation log. A model never
 * writes PHP through this door.
 *
 * Pure like the ER1 derivations, apart from the warnings it collects for the log.
 *
 * @since  1.6.0
 */
final class LedgerDerivations
{
    /**
     * Problems in the model, for the log.
     *
     * @var    string[]
     * @since  1.6.0
     */
    private array $warnings = [];

    /**
     * Problems found while deriving, for the generation log.
     *
     * @return  string[]
     *
     * @since   1.6.0
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * The account roles, as PHP literals.
     *
     * @param   object  $model  The decoded project.
     *
     * @return  array<int, array{key: string, name: string, nature: string, normalBalance: string}>
     *
     * @since   1.6.0
     */
    public function accountRoles(object $model): array
    {
        $roles = [];

        foreach ($this->items($model->account_roles ?? null) as $role) {
            $key = trim((string) ($role->key ?? ''));

            if ($key === '') {
                continue;
            }

            $roles[] = [
                'key'           => var_export($key, true),
                'name'          => var_export((string) ($role->name ?? ''), true),
                'nature'        => var_export((string) ($role->nature ?? ''), true),
                'normalBalance' => var_export(strtolower((string) ($role->normal_balance ?? '')), true),
            ];
        }

        return $roles;
    }

    /**
     * The posting rules, with each line's amount as a PHP expression.
     *
     * @param   object  $model  The decoded project.
     *
     * @return  array<int, array{event: string, description: string, lines: array<int, array{role: string, side: string, amount: string}>}>
     *
     * @since   1.6.0
     */
    public function postingRules(object $model): array
    {
        $roles = [];

        foreach ($this->items($model->account_roles ?? null) as $role) {
            $roles[trim((string) ($role->key ?? ''))] = true;
        }

        $rules = [];

        foreach ($this->items($model->posting_rules ?? null) as $rule) {
            $event = trim((string) ($rule->event_name ?? ''));

            if ($event === '') {
                continue;
            }

            $lines = [];

            foreach ($this->items($rule->lines ?? null) as $line) {
                $role = trim((string) ($line->role ?? ''));
                $side = strtolower(trim((string) ($line->side ?? '')));

                if (!isset($roles[$role])) {
                    $this->warn('posting rule "' . $event . '" posts to the account role "' . $role
                        . '", which the project does not declare');
                }

                $amount = \in_array($side, ['debit', 'credit'], true)
                    ? $this->amount((string) ($line->amount ?? ''))
                    : null;

                if ($amount === null) {
                    $this->warn('posting rule "' . $event . '" has a line that cannot be generated: side "' . $side
                        . '", amount "' . (string) ($line->amount ?? '') . '". Posting this event throws.');

                    $amount = '$this->unreadable(' . var_export($event, true) . ')';
                } else {
                    // Rounded to cents once, after the exact arithmetic: 6.9.
                    $amount = '$this->cents(' . $amount . ')';
                }

                $lines[] = [
                    'role'   => var_export($role, true),
                    'side'   => $side === 'credit' ? 'JournalLine::CREDIT' : 'JournalLine::DEBIT',
                    'amount' => $amount,
                ];
            }

            $rules[] = [
                'event'       => var_export($event, true),
                'description' => var_export((string) ($rule->description ?? ''), true),
                'lines'       => $lines,
            ];
        }

        return $rules;
    }

    /**
     * An amount expression as PHP, or null when it is not arithmetic over names and numbers.
     *
     * `amount - vat` becomes
     * `$this->minus($this->value($data, 'amount'), $this->value($data, 'vat'))`.
     *
     * Calls rather than PHP's own operators since 6.9: the generated class does
     * its arithmetic on exact fractions of integers, and rounds a line to cents
     * once, at the end. PHP's `-` on floats had `0.1 + 0.2` come out as
     * `0.30000000000000004`, rounded every line on its own and compared the
     * balance with a tolerance. So the expression is parsed with the usual
     * precedence here, `*` and `/` before `+` and `-`, a leading minus
     * binding tightest, and written as the calls that precedence means.
     *
     * @param   string  $expression  As the model writes it.
     *
     * @return  ?string
     *
     * @since   1.6.0
     */
    public function amount(string $expression): ?string
    {
        $expression = trim($expression);

        if ($expression === '') {
            return null;
        }

        if (!preg_match_all('/\s*(?:([A-Za-z_][A-Za-z0-9_]*)|(\d+(?:\.\d+)?)|([-+*\/()]))/A', $expression, $tokens, \PREG_SET_ORDER)) {
            return null;
        }

        // Every character has to belong to a token, or something slipped past.
        if (implode('', array_column($tokens, 0)) !== $expression) {
            return null;
        }

        $tokens = array_map(
            static fn (array $token): array => [
                'name'     => $token[1] ?? '',
                'number'   => $token[2] ?? '',
                'operator' => $token[3] ?? '',
            ],
            $tokens
        );

        $position = 0;
        $php      = $this->sum($tokens, $position);

        return $php !== null && $position === \count($tokens) ? $php : null;
    }

    /**
     * Terms joined by `+` and `-`, left to right.
     *
     * @param   array<int, array{name: string, number: string, operator: string}>  $tokens    The expression.
     * @param   int                                                                 $position  Where to read, moved past what is read.
     *
     * @return  ?string
     *
     * @since   1.6.6
     */
    private function sum(array $tokens, int &$position): ?string
    {
        $php = $this->product($tokens, $position);

        while ($php !== null && \in_array($tokens[$position]['operator'] ?? '', ['+', '-'], true)) {
            $method = $tokens[$position++]['operator'] === '+' ? 'plus' : 'minus';
            $right  = $this->product($tokens, $position);
            $php    = $right === null ? null : '$this->' . $method . '(' . $php . ', ' . $right . ')';
        }

        return $php;
    }

    /**
     * Factors joined by `*` and `/`, left to right.
     *
     * @param   array<int, array{name: string, number: string, operator: string}>  $tokens    The expression.
     * @param   int                                                                 $position  Where to read, moved past what is read.
     *
     * @return  ?string
     *
     * @since   1.6.6
     */
    private function product(array $tokens, int &$position): ?string
    {
        $php = $this->factor($tokens, $position);

        while ($php !== null && \in_array($tokens[$position]['operator'] ?? '', ['*', '/'], true)) {
            $method = $tokens[$position++]['operator'] === '*' ? 'times' : 'dividedBy';
            $right  = $this->factor($tokens, $position);
            $php    = $right === null ? null : '$this->' . $method . '(' . $php . ', ' . $right . ')';
        }

        return $php;
    }

    /**
     * A name, a number, a bracketed sum, or any of those after a minus.
     *
     * @param   array<int, array{name: string, number: string, operator: string}>  $tokens    The expression.
     * @param   int                                                                 $position  Where to read, moved past what is read.
     *
     * @return  ?string
     *
     * @since   1.6.6
     */
    private function factor(array $tokens, int &$position): ?string
    {
        $token = $tokens[$position++] ?? null;

        if ($token === null) {
            return null;
        }

        if ($token['name'] !== '') {
            return '$this->value($data, ' . var_export($token['name'], true) . ')';
        }

        if ($token['number'] !== '') {
            return '$this->number(' . var_export($token['number'], true) . ')';
        }

        if ($token['operator'] === '-') {
            $operand = $this->factor($tokens, $position);

            return $operand === null ? null : '$this->negated(' . $operand . ')';
        }

        if ($token['operator'] === '(') {
            $inner = $this->sum($tokens, $position);

            if ($inner === null || ($tokens[$position++]['operator'] ?? '') !== ')') {
                return null;
            }

            return $inner;
        }

        return null;
    }

    /**
     * The members of a repeating group, whether stored as an object or a list.
     *
     * @param   mixed  $group  `{"x0": {...}}`, `[{...}]` or nothing.
     *
     * @return  object[]
     *
     * @since   1.6.0
     */
    private function items(mixed $group): array
    {
        if (!\is_object($group) && !\is_array($group)) {
            return [];
        }

        return array_values(array_filter((array) $group, 'is_object'));
    }

    /**
     * Note a problem, escaped: the log is HTML and the text comes from the model.
     *
     * @param   string  $message  What went wrong.
     *
     * @return  void
     *
     * @since   1.6.0
     */
    private function warn(string $message): void
    {
        $this->warnings[] = htmlspecialchars($message, \ENT_QUOTES, 'UTF-8');
    }
}
