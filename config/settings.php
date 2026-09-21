<?php

return [
    'late_penalty_config' => [
        'grace_period_days' => 0,
        'calculation_type' => 'fixed',
        'amount' => 500, //cent
        'maximum_amount' => 0,
        'frequency' => 1,
        'applicable_categories' => ['rent'],
    ],
    'fee_types_config' => [
        'rent_daily_rental'                    => ['is_active' => true],
        'rent_weekly_rental'                   => ['is_active' => true],
        'rent_monthly_rental'                  => ['is_active' => true],
        'rent_yearly_rental'                   => ['is_active' => true],
        'service_agreement_fee'                => ['is_active' => true],
        'service_late_payment_penalty'         => ['is_active' => true],
        'deposit_security_deposit'             => ['is_active' => true],
        'deposit_utilities_deposit'            => ['is_active' => true],
        'deposit_security_utilities_deposit'   => ['is_active' => true],
        'management_management_fee'            => ['is_active' => true],
    ],
    'due_date_config' => [
        'days' => 7,
    ],
    'pending_renewal_config' => [
        'number' => 3,
        'mode' => 'months',
        'days' => 90
    ],
];