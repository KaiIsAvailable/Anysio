<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserManagement;
use App\Models\RefCodePackage;
use App\Models\FeeType;
use App\FeeTypeCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // Create system admin
            $admin = $this->createPmsUserWithRefCode(
                'System Admin',
                'admin@anysio.com',
                'admin',
                null,
                true
            );

            // Create default fee types
            $this->createDefaultFeeTypes($admin);
        });
    }

    private function createPmsUserWithRefCode(
        $name,
        $email,
        $role,
        $referredBy = null,
        $isOfficial = false
    ) {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);

        UserManagement::create([
            'user_id' => $user->id,
            'package_id' => $referredBy,
            'role' => $role,
            'subscription_status' => 'active',
        ]);

        RefCodePackage::updateOrCreate(
            ['ref_code' => 'P1_MONTHLY'],
            [
                'name'              => 'Starter Plan (1%)',
                'price_mode'        => 'monthly',
                'price'             => 0,
                'commission_rate'   => 100,
                'max_lease_limit'   => 5,
                'extra_lease_price' => 500,
            ]
        );

        return $user;
    }

    private function createDefaultFeeTypes(User $user): void
    {
        $feeTypes = [

            // Rent
            [
                'name' => 'Daily Fee',
                'category' => FeeTypeCategory::RENT,
            ],
            [
                'name' => 'Weekly Fee',
                'category' => FeeTypeCategory::RENT,
            ],
            [
                'name' => 'Monthly Fee',
                'category' => FeeTypeCategory::RENT,
            ],
            [
                'name' => 'Yearly Fee',
                'category' => FeeTypeCategory::RENT,
            ],

            // Deposits
            [
                'name' => 'Security Deposit',
                'category' => FeeTypeCategory::DEPOSIT,
            ],
            [
                'name' => 'Utilities Deposit',
                'category' => FeeTypeCategory::DEPOSIT,
            ],
            [
                'name' => 'Security & Utilities Deposit',
                'category' => FeeTypeCategory::DEPOSIT,
            ],
        ];

        foreach ($feeTypes as $feeType) {
            FeeType::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name' => $feeType['name'],
                    'category' => $feeType['category'],
                ],
                [
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }
}