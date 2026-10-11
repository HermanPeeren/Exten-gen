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
 * is refused.
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
                new JournalLine('bank.psp', JournalLine::DEBIT, $this->value($data, 'amount')),
                new JournalLine('liability.tickets', JournalLine::CREDIT, $this->value($data, 'amount') - $this->value($data, 'vat')),
                new JournalLine('vat.output.low', JournalLine::CREDIT, $this->value($data, 'vat')),
            ],
            'FlightFlown' => [
                new JournalLine('liability.tickets', JournalLine::DEBIT, $this->value($data, 'amount')),
                new JournalLine('revenue.flights', JournalLine::CREDIT, $this->value($data, 'amount')),
            ],
            'TicketExpired' => [
                new JournalLine('liability.tickets', JournalLine::DEBIT, $this->value($data, 'amount')),
                new JournalLine('revenue.breakage', JournalLine::CREDIT, $this->value($data, 'amount')),
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
     * @param   string         $event  For the message.
     * @param   JournalLine[]  $lines  The entry.
     *
     * @return  void
     */
    private function assertBalanced(string $event, array $lines): void
    {
        $balance = 0.0;

        foreach ($lines as $line) {
            $balance += $line->side === JournalLine::DEBIT ? $line->amount : -$line->amount;
        }

        if (round($balance, 2) !== 0.0) {
            throw new \DomainException(
                'The entry for "' . $event . '" does not balance: debit and credit differ by ' . round($balance, 2) . '.'
            );
        }
    }

    /**
     * A number from the event's data.
     *
     * @param   array<string, mixed>  $data  The event's data.
     * @param   string                $name  The field an amount expression names.
     *
     * @return  float
     */
    private function value(array $data, string $name): float
    {
        if (!isset($data[$name]) || !is_numeric($data[$name])) {
            throw new \DomainException('The event has no number called "' . $name . '".');
        }

        return (float) $data[$name];
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
