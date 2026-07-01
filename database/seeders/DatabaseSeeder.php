<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserManagement;
use App\Models\RefCodePackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // 只保留系统管理员 admin@anysio.com
            $this->createPmsUserWithRefCode(
                'System Admin', 
                'admin@anysio.com', 
                'admin', 
                null, 
                true  
            );
        });
    }

    private function createPmsUserWithRefCode($name, $email, $role, $referredBy = null, $isOfficial = false)
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'), // 你的登录密码
            'role' => $role,
            'email_verified_at' => now(), 
        ]);

        UserManagement::create([
            'user_id' => $user->id,
            'package_id' => $referredBy,
            'role' => $role,
            'subscription_status' => 'active',
        ]);

        // 确保套餐存在，防止登录后读取数据报错
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