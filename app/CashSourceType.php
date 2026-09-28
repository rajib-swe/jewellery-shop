<?php

namespace App;

/**
 * Where a cash transaction came from, so the ledger row can be traced back to
 * the document that produced it and a reversal can find it again.
 */
enum CashSourceType: string
{
    case SalePayment = 'sale_payment';
    case PawnDisbursement = 'pawn_disbursement';
    case PawnPayment = 'pawn_payment';
    case SupplierPayment = 'supplier_payment';
    case Expense = 'expense';
    case CashAdjustment = 'cash_adjustment';
}
