<?php
namespace App\Enums;

enum RefundItemType: string {
    case REFUNDABLE_AMOUNT = 'refundable_amount';
    case DAMAGE = 'damage';
    case UTILITY = 'utility';
    case CLEANING = 'cleaning';
    case ADJUSTMENT = 'adjustment';
}