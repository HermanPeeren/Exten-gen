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
 * The account roles this component posts to.
 *
 * A role is a stable key with a nature and a normal balance. A chart of
 * accounts maps each role to one of its accounts; the posting rules only
 * ever name the role.
 */
final class AccountRoles
{
    /**
     * Every role, by key.
     *
     * @var array<string, array{name: string, nature: string, normalBalance: string}>
     */
    public const ROLES = [
        'bank.psp' => ['name' => 'Bank: payment provider', 'nature' => 'asset', 'normalBalance' => 'debit'],
        'cash' => ['name' => 'Cash', 'nature' => 'asset', 'normalBalance' => 'debit'],
        'liability.tickets' => ['name' => 'Liability: tickets not yet flown', 'nature' => 'liability', 'normalBalance' => 'credit'],
        'vat.output.low' => ['name' => 'VAT payable 9%', 'nature' => 'liability', 'normalBalance' => 'credit'],
        'revenue.flights' => ['name' => 'Revenue: flights', 'nature' => 'income', 'normalBalance' => 'credit'],
        'revenue.breakage' => ['name' => 'Revenue: breakage (expired tickets)', 'nature' => 'income', 'normalBalance' => 'credit'],
        'expense.landowner' => ['name' => 'Expense: landowner compensation', 'nature' => 'expense', 'normalBalance' => 'debit'],
    ];

    /**
     * Whether a role is one of this component's.
     */
    public static function has(string $role): bool
    {
        return isset(self::ROLES[$role]);
    }
}
