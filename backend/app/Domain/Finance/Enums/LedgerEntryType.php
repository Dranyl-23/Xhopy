<?php

namespace App\Domain\Finance\Enums;

enum LedgerEntryType: string
{
    case OrderEarning = 'order_earning';
    case OrderReversal = 'order_reversal';
    case PayoutDebit = 'payout_debit';
    case PayoutRejectionCredit = 'payout_rejection_credit';

    public function label(): string
    {
        return match ($this) {
            self::OrderEarning => 'Order Net Earning',
            self::OrderReversal => 'Order Reversal',
            self::PayoutDebit => 'Payout Withdrawal',
            self::PayoutRejectionCredit => 'Payout Refunded to Balance',
        };
    }
}
