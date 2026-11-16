<?php

use App\FeeTypeCategory;

return [
    ['name' => 'Daily Rental', 'category' => FeeTypeCategory::RENT->value],
    ['name' => 'Weekly Rental', 'category' => FeeTypeCategory::RENT->value],
    ['name' => 'Monthly Rental', 'category' => FeeTypeCategory::RENT->value],
    ['name' => 'Yearly Rental', 'category' => FeeTypeCategory::RENT->value],
    ['name' => 'Agreement Fee', 'category' => FeeTypeCategory::SERVICE->value],
    ['name' => 'Late Payment Penalty', 'category' => FeeTypeCategory::SERVICE->value],
    ['name' => 'Security Deposit', 'category' => FeeTypeCategory::DEPOSIT->value],
    ['name' => 'Utilities Deposit', 'category' => FeeTypeCategory::DEPOSIT->value],
    ['name' => 'Security & Utilities Deposit', 'category' => FeeTypeCategory::DEPOSIT->value],
    ['name' => 'Management Fee', 'category' => FeeTypeCategory::MANAGEMENT->value],
];
