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

    public readonly float $amount;

    /**
     * @param  string  $role    An account role's key, such as 'liability.vouchers'.
     * @param  string  $side    self::DEBIT or self::CREDIT.
     * @param  float   $amount  Rounded to cents.
     */
    public function __construct(
        public readonly string $role,
        public readonly string $side,
        float $amount
    ) {
        $this->amount = round($amount, 2);
    }
}
