<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\FeeType;
use App\FeeTypeCategory;
use Illuminate\Database\Seeder;

class FeeTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Find the system admin
        $admin = User::where('role', 'admin')->first();

        if (!$admin) {
            return;
        }

        $feeTypes = [
            // Rent
            ['name' => 'Daily Rental', 'category' => FeeTypeCategory::RENT],
            ['name' => 'Weekly Rental', 'category' => FeeTypeCategory::RENT],
            ['name' => 'Monthly Rental', 'category' => FeeTypeCategory::RENT],
            ['name' => 'Yearly Rental', 'category' => FeeTypeCategory::RENT],

            // Deposits
            ['name' => 'Security Deposit', 'category' => FeeTypeCategory::DEPOSIT],
            ['name' => 'Utilities Deposit', 'category' => FeeTypeCategory::DEPOSIT],
            ['name' => 'Security & Utilities Deposit', 'category' => FeeTypeCategory::DEPOSIT],
            
            // Management Fee
            ['name' => 'Management Fee', 'category' => FeeTypeCategory::MANAGEMENT],
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::updateOrCreate(
                [
                    // Attributes to check if the record already exists
                    'name' => $feeType['name'],
                    'user_id' => $admin->id, 
                ],
                [
                    // Attributes to update or create
                    'category' => $feeType['category'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}