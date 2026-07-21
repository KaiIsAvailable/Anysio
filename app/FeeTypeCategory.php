<?php

namespace App;

enum FeeTypeCategory: string
{
    case RENT = 'rent';
    case DEPOSIT = 'deposit';
    case UTILITY = 'utility';
    case SERVICE = 'service';
    case PENALTY = 'penalty';
}