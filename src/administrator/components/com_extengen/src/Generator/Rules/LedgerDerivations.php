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
 * `amount - vat`. It becomes PHP here, at generation time, so the generated
 * class reads like a hand-written one. That is also why it is parsed rather
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
     * `amount - vat` becomes `$this->value($data, 'amount') - $this->value($data, 'vat')`.
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

        $php           = '';
        $expectOperand = true;
        $depth         = 0;

        foreach ($tokens as $token) {
            $name     = $token[1] ?? '';
            $number   = $token[2] ?? '';
            $operator = $token[3] ?? '';

            if ($expectOperand) {
                if ($name !== '') {
                    $php .= '$this->value($data, ' . var_export($name, true) . ')';
                    $expectOperand = false;
                } elseif ($number !== '') {
                    $php .= $number;
                    $expectOperand = false;
                } elseif ($operator === '(') {
                    $php .= '(';
                    $depth++;
                } elseif ($operator === '-') {
                    $php .= '-';
                } else {
                    return null;
                }

                continue;
            }

            if ($operator === ')') {
                if (--$depth < 0) {
                    return null;
                }

                $php .= ')';
            } elseif (\in_array($operator, ['+', '-', '*', '/'], true)) {
                $php .= ' ' . $operator . ' ';
                $expectOperand = true;
            } else {
                return null;
            }
        }

        return !$expectOperand && $depth === 0 ? $php : null;
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
