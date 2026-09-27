<?php

namespace App\Enums;

/**
 * Ledger entry type.
 */
enum TransactionType: string
{
    case Deposit = 'deposit';
    case EscrowHold = 'escrow_hold';
    case EscrowRelease = 'escrow_release';
    case Payout = 'payout';
    case Refund = 'refund';
    case Fee = 'fee';
    case PlanPurchase = 'plan_purchase';
    case MentorPayout = 'mentor_payout';
    case ExamFee = 'exam_fee';
    case MentorshipFee = 'mentorship_fee';
}
