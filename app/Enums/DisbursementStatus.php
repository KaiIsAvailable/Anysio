<?php
namespace App\Enums;

enum DisbursementStatus: string {
    case PENDING = 'pending';
    case SUCCESSFUL = 'successful';
    case FAILED = 'failed';
}