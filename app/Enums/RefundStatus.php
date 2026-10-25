<?php
namespace App\Enums;

enum RefundStatus: string {
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PAID = 'paid';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}