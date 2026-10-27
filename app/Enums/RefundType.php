<?php
namespace App\Enums;

enum RefundType: string {
    case DEPOSIT = 'deposit';
    case PAYMENT_REVERSAL = 'payment_reversal';
    case OVERPAYMENT = 'overpayment';
}

enum DisbursementStatus: string {
    case PENDING = 'pending';
    case SUCCESSFUL = 'successful';
    case FAILED = 'failed';
}