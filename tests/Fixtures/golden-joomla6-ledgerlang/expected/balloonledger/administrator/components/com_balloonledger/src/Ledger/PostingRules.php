<?php
/**
 * @package    BalloonLedger
 * @subpackage BalloonLedger
 * @version    0.1.0
 *
 * @copyright  Yepr, Herman Peeren
 * @license    GPL3
 */

namespace Yepr\Component\BalloonLedger\Administrator\Ledger;

defined('_JEXEC') or die;

/**
 * The posting rules: which journal entry each business event becomes.
 *
 * Generated from the model's posting rules. Each line posts an amount to an
 * account role, on the debit or the credit side, and every entry balances or
 * is refused. Amounts are computed exactly and rounded to cents once per line;
 * the arithmetic is at the bottom of the class.
 */
final class PostingRules
{
    /**
     * The events there is a rule for, with what each one means.
     *
     * @var array<string, string>
     */
    public const EVENTS = [
        'TicketSold' => 'A ticket is paid for. VAT at the low rate is due now; the rest is owed as a flight.',
        'FlightFlown' => 'The ticket is used by flying. What was owed is now revenue; the VAT was due at the sale.',
        'TicketExpired' => 'The ticket was never used. What was owed becomes breakage revenue; the VAT was due at the sale.',
        'FlightCancelledDueToWind' => 'No entry: the ticket stays a liability until it is flown or expires.',
    ];

    /**
     * The lines of the journal entry an event becomes.
     *
     * @param   string                $event  One of the keys of self::EVENTS.
     * @param   array<string, mixed>  $data   The event's data, such as 'amount' and 'vat'.
     *
     * @return  JournalLine[]  An empty list when the event posts nothing.
     *
     * @throws  \DomainException  When there is no rule for the event, or the entry does not balance.
     */
    public function linesFor(string $event, array $data): array
    {
        $lines = match ($event) {
            'TicketSold' => [
                new JournalLine('bank.psp', JournalLine::DEBIT, $this->cents($this->value($data, 'amount'))),
                new JournalLine('liability.tickets', JournalLine::CREDIT, $this->cents($this->minus($this->value($data, 'amount'), $this->value($data, 'vat')))),
                new JournalLine('vat.output.low', JournalLine::CREDIT, $this->cents($this->value($data, 'vat'))),
            ],
            'FlightFlown' => [
                new JournalLine('liability.tickets', JournalLine::DEBIT, $this->cents($this->value($data, 'amount'))),
                new JournalLine('revenue.flights', JournalLine::CREDIT, $this->cents($this->value($data, 'amount'))),
            ],
            'TicketExpired' => [
                new JournalLine('liability.tickets', JournalLine::DEBIT, $this->cents($this->value($data, 'amount'))),
                new JournalLine('revenue.breakage', JournalLine::CREDIT, $this->cents($this->value($data, 'amount'))),
            ],
            'FlightCancelledDueToWind' => [],
            default => throw new \DomainException('There is no posting rule for the event "' . $event . '".'),
        };

        $this->assertBalanced($event, $lines);

        return $lines;
    }

    /**
     * Refuse an entry whose debits and credits differ.
     *
     * Compared in whole cents, so balanced means equal: no tolerance, and no
     * float that is almost zero.
     *
     * @param   string         $event  For the message.
     * @param   JournalLine[]  $lines  The entry.
     *
     * @return  void
     */
    private function assertBalanced(string $event, array $lines): void
    {
        $balance = 0;

        foreach ($lines as $line) {
            $balance += $line->side === JournalLine::DEBIT ? $line->cents : -$line->cents;
        }

        if ($balance !== 0) {
            throw new \DomainException(
                'The entry for "' . $event . '" does not balance: debit and credit differ by '
                . JournalLine::format($balance) . '.'
            );
        }
    }

    /*
     * Exact arithmetic.
     *
     * Money is never a float here. An amount is a fraction of two integers,
     * [numerator, denominator], with the denominator positive and the fraction
     * reduced. Sums, differences, products and quotients stay exact, so
     * `amount * 9 / 109` is the exact VAT in a gross amount, and a line is
     * rounded to cents once, at the end, half away from zero.
     *
     * Integers rather than bcmath: Joomla does not require bcmath, and a
     * generated component must install wherever Joomla does. A result too
     * large for PHP's integers is refused rather than quietly becoming a float.
     */

    /**
     * A number from the event's data.
     *
     * An integer, or a decimal string such as '218.00'. A float is read as
     * the shortest decimal that spells it, so 0.1 means one tenth.
     *
     * @param   array<string, mixed>  $data  The event's data.
     * @param   string                $name  The field an amount expression names.
     *
     * @return  array{int, int}
     */
    private function value(array $data, string $name): array
    {
        $value = $data[$name] ?? null;

        if (\is_float($value) && is_finite($value)) {
            $value = var_export($value, true);
        }

        if (\is_int($value)) {
            return [$value, 1];
        }

        if (!\is_string($value) || !preg_match('/^\s*[-+]?(\d+(\.\d*)?|\.\d+)([eE][-+]?\d+)?\s*$/', $value)) {
            throw new \DomainException('The event has no number called "' . $name . '".');
        }

        return $this->number($value);
    }

    /**
     * A decimal written out, as an exact fraction.
     *
     * @param   string  $decimal  Such as '0.09', '-12.5' or '1.0E+3'.
     *
     * @return  array{int, int}
     */
    private function number(string $decimal): array
    {
        preg_match('/^\s*([-+]?)(\d*)(?:\.(\d*))?(?:[eE]([-+]?\d+))?\s*$/', $decimal, $parts);

        $digits   = ltrim(($parts[2] ?? '') . ($parts[3] ?? ''), '0');
        $exponent = (int) ($parts[4] ?? 0) - \strlen($parts[3] ?? '');

        if ($digits === '') {
            return [0, 1];
        }

        $numerator   = $this->checked($digits);
        $denominator = 1;

        for (; $exponent > 0; $exponent--) {
            $numerator = $this->checked($numerator * 10);
        }

        for (; $exponent < 0; $exponent++) {
            $denominator = $this->checked($denominator * 10);
        }

        return $this->reduced(($parts[1] ?? '') === '-' ? -$numerator : $numerator, $denominator);
    }

    /**
     * a + b
     *
     * @param   array{int, int}  $a  A fraction.
     * @param   array{int, int}  $b  A fraction.
     *
     * @return  array{int, int}
     */
    private function plus(array $a, array $b): array
    {
        return $this->reduced(
            $this->checked($this->checked($a[0] * $b[1]) + $this->checked($b[0] * $a[1])),
            $this->checked($a[1] * $b[1])
        );
    }

    /**
     * a - b
     *
     * @param   array{int, int}  $a  A fraction.
     * @param   array{int, int}  $b  A fraction.
     *
     * @return  array{int, int}
     */
    private function minus(array $a, array $b): array
    {
        return $this->plus($a, $this->negated($b));
    }

    /**
     * a * b
     *
     * @param   array{int, int}  $a  A fraction.
     * @param   array{int, int}  $b  A fraction.
     *
     * @return  array{int, int}
     */
    private function times(array $a, array $b): array
    {
        return $this->reduced($this->checked($a[0] * $b[0]), $this->checked($a[1] * $b[1]));
    }

    /**
     * a / b
     *
     * @param   array{int, int}  $a  A fraction.
     * @param   array{int, int}  $b  A fraction, not zero.
     *
     * @return  array{int, int}
     */
    private function dividedBy(array $a, array $b): array
    {
        if ($b[0] === 0) {
            throw new \DomainException('An amount in a posting rule divides by zero.');
        }

        return $this->reduced($this->checked($a[0] * $b[1]), $this->checked($a[1] * $b[0]));
    }

    /**
     * -a
     *
     * @param   array{int, int}  $a  A fraction.
     *
     * @return  array{int, int}
     */
    private function negated(array $a): array
    {
        return [$this->checked(-$a[0]), $a[1]];
    }

    /**
     * A fraction in whole cents, rounded half away from zero.
     *
     * @param   array{int, int}  $a  A fraction.
     *
     * @return  int
     */
    private function cents(array $a): int
    {
        $scaled    = $this->checked($a[0] * 100);
        $cents     = intdiv($scaled, $a[1]);
        $remainder = $scaled % $a[1];

        if (2 * abs($remainder) >= $a[1]) {
            $cents += $scaled < 0 ? -1 : 1;
        }

        return $cents;
    }

    /**
     * A fraction with its sign on the numerator and no common factor.
     *
     * @param   int  $numerator    The numerator.
     * @param   int  $denominator  The denominator, not zero.
     *
     * @return  array{int, int}
     */
    private function reduced(int $numerator, int $denominator): array
    {
        if ($denominator < 0) {
            [$numerator, $denominator] = [$this->checked(-$numerator), $this->checked(-$denominator)];
        }

        $a = abs($numerator);
        $b = $denominator;

        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a > 1 ? [intdiv($numerator, $a), intdiv($denominator, $a)] : [$numerator, $denominator];
    }

    /**
     * An integer that stayed one: PHP turns an overflow into a float.
     *
     * @param   int|float|string  $result  What an operation produced.
     *
     * @return  int
     */
    private function checked(int|float|string $result): int
    {
        if (\is_string($result) && preg_match('/^\d+$/', $result) && \strlen($result) <= 18) {
            return (int) $result;
        }

        if (!\is_int($result)) {
            throw new \DomainException('An amount in a posting rule is too large to compute exactly.');
        }

        return $result;
    }

    /**
     * A line the generator could not read: its model needs correcting.
     *
     * @param   string  $event  The event whose rule holds it.
     *
     * @return  never
     */
    private function unreadable(string $event): never
    {
        throw new \DomainException('The posting rule for "' . $event . '" has a line that could not be generated.');
    }
}
