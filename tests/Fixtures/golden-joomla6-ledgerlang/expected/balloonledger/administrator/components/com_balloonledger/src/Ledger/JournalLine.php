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
 * One line of a journal entry: an account role, debit or credit, and an amount.
 *
 * A line posts to a role, never to an account number. Which account a role is
 * depends on the chart of accounts a site uses, so the same posting rules work
 * with every chart.
 */
final class JournalLine
{
    public const DEBIT = 'debit';

    public const CREDIT = 'credit';

    /**
     * The amount in whole cents: what the arithmetic and the balance use.
     */
    public readonly int $cents;

    /**
     * The amount as a decimal string with two places, such as '218.00': what a
     * DECIMAL(15,2) column stores without a float in between.
     */
    public readonly string $amount;

    /**
     * @param  string  $role   An account role's key, such as 'liability.tickets'.
     * @param  string  $side   self::DEBIT or self::CREDIT.
     * @param  int     $cents  The amount in whole cents, already rounded.
     */
    public function __construct(
        public readonly string $role,
        public readonly string $side,
        int $cents
    ) {
        $this->cents  = $cents;
        $this->amount = self::format($cents);
    }

    /**
     * Cents as a decimal string: 21800 is '218.00', -5 is '-0.05'.
     *
     * @param   int  $cents  An amount in cents.
     *
     * @return  string
     */
    public static function format(int $cents): string
    {
        $sign     = $cents < 0 ? '-' : '';
        $absolute = (string) abs($cents);
        $absolute = str_pad($absolute, 3, '0', STR_PAD_LEFT);

        return $sign . substr($absolute, 0, -2) . '.' . substr($absolute, -2);
    }
}
