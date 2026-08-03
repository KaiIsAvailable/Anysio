<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\FeeType;
use App\FeeTypeCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class FeeTypeSeeder extends Seeder
{
    public function run(): void
    {
        // Find the system admin
        $admin = User::where('role', 'admin')->first();

        if (!$admin) {
            return;
        }

        // Temporarily disable foreign key checks if leases are linked to fee types
        Schema::disableForeignKeyConstraints();

        // Clear all old fee type data
        FeeType::truncate();

        // Re-enable foreign key checks
        Schema::enableForeignKeyConstraints();

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
            FeeType::create([
                'user_id' => $admin->id,
                'name' => $feeType['name'],
                'category' => $feeType['category'],
                'is_system' => true,
                'is_active' => true,
            ]);
        }
    }
}