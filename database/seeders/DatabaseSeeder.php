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

            // Create or update system admin safely
            $admin = $this->createPmsUserWithRefCode(
                'System Admin',
                'admin@anysio.com',
                'admin',
                null,
                true
            );

            // Call your DocumentTemplateSeeder
            $this->call([
                DocumentTemplateSeeder::class,
            ]);
            
        });
    }

    private function createPmsUserWithRefCode($name, $email, $role, $referredBy = null, $isOfficial = false) {
        // Use updateOrCreate on User based on email
        $user = User::updateOrCreate(
            ['email' => $email], // Look for existing user by email
            [
                'name' => $name,
                'password' => Hash::make('password123'),
                'role' => $role,
                'email_verified_at' => now(),
            ]
        );

        // Use updateOrCreate on UserManagement based on user_id
        UserManagement::updateOrCreate(
            ['user_id' => $user->id], // Look for existing management profile by user_id
            [
                'package_id' => $referredBy,
                'role' => $role,
                'subscription_status' => 'active',
            ]
        );

        // RefCodePackage already uses updateOrCreate, which is great!
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
}