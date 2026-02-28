<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserManagement;
use App\Models\Owners;
use App\Models\Room;
use App\Models\Asset; // 必须引入新的 Asset 模型
use App\Models\Tenants;
use App\Models\EmergencyContact;
use App\Models\RefCodePackage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // --- 1. 系统管理员 (Admin) ---
            $adminUser = $this->createPmsUserWithRefCode(
                'System Admin', 
                'admin@anysio.com', 
                'admin', 
                null, 
                true  
            );

            // --- 2. 高级房东 (Owner Admin) ---
            $ownerAdminUser = $this->createPmsUserWithRefCode(
                'Main Owner Boss', 
                'owneradmin@anysio.com', 
                'ownerAdmin', 
                $adminUser->id
            );

            Owners::create([
                'user_id' => $ownerAdminUser->id,
                'company_name' => 'Anysio Real Estate',
                'ic_number' => '880101-14-1111',
                'phone' => '0121112222',
                'gender' => 'male',
            ]);

            // --- 3. 预设资产库 (Asset Library) ---
            // 因为现在是多对多，我们需要先有一些“资产模板”
            $assetNames = ['Air Conditioner', 'Washing Machine', 'Refrigerator', 'Single Bed', 'Study Table'];
            $assets = [];
            foreach ($assetNames as $name) {
                $assets[] = Asset::create([
                    'user_id' => $ownerAdminUser->id, // 假设资产库属于 Owner Admin
                    'name' => $name,
                ]);
            }

            // --- 4. 普通房东 (Owner) 循环 ---
            $ownersData = [
                ['name' => 'Michael Scott', 'email' => 'michael@anysio.com', 'company' => 'Dunder Mifflin'],
                ['name' => 'Harvey Specter', 'email' => 'harvey@anysio.com', 'company' => 'Specter Realty'],
                ['name' => 'Bruce Wayne', 'email' => 'bruce@anysio.com', 'company' => 'Wayne Ent'],
            ];

            foreach ($ownersData as $index => $data) {
                $user = $this->createPmsUserWithRefCode(
                    $data['name'], 
                    $data['email'], 
                    'owner', 
                    $ownerAdminUser->id
                );

                $ownerProfile = Owners::create([
                    'user_id' => $user->id,
                    'company_name' => $data['company'],
                    'ic_number' => '70010' . ($index + 1) . '-10-' . rand(1000, 9999),
                    'phone' => '01' . rand(10000000, 99999999),
                    'gender' => ($index % 2 == 0) ? 'male' : 'female',
                ]);

                // 房产和房间生成逻辑
                for ($r = 1; $r <= 2; $r++) {
                    $room = Room::create([
                        'owner_id' => $ownerProfile->id,
                        'room_no' => 'A-' . ($index + 1) . '-0' . $r,
                        'room_type' => ($r == 1) ? 'Master Room' : 'Single Room',
                        'status' => 'Vacant',
                        'address' => 'No ' . ($index + 1) . ', Jalan Anysio, 50450 Kuala Lumpur',
                    ]);

                    // --- 重点：Many-to-Many 关联资产 ---
                    // 随机给每个房间分配 2-3 个资产
                    $randomAssets = collect($assets)->random(rand(2, 3));
                    
                    foreach ($randomAssets as $asset) {
                        $room->assets()->attach($asset->id, [
                            'id' => (string) Str::ulid(), // 中间表主键
                            'condition' => 'Good',
                            'last_maintenance' => now()->subMonths(rand(1, 6)),
                            'remark' => 'Standard Furnished',
                        ]);
                    }
                }
            }

            // --- 5. 普通租客 (Tenant) ---
            $tenantsList = [
                ['name' => 'John Doe', 'email' => 'john@anysio.com'],
                ['name' => 'Siti Nurhaliza', 'email' => 'siti@anysio.com'],
            ];

            foreach ($tenantsList as $index => $data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password123'),
                    'role' => 'tenant',
                    'email_verified_at' => now(), // 租客也验证
                ]);

                $tenantProfile = Tenants::create([
                    'user_id' => $user->id,
                    'phone' => '01' . rand(10000000, 99999999),
                    'nationality' => 'Malaysian',
                    'gender' => ($index % 2 == 0) ? 'male' : 'female',
                ]);
            }
        });
    }

    private function createPmsUserWithRefCode($name, $email, $role, $referredBy = null, $isOfficial = false)
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
            'email_verified_at' => now(), // 按照你的要求，这里加上 verified_at
        ]);

        $userMgmt = UserManagement::create([
            'user_id' => $user->id,
            'referred_by' => $referredBy,
            'role' => $role,
            'subscription_status' => 'active',
        ]);

        $formattedName = Str::upper(str_replace(' ', '_', $name));
        $refCode = $formattedName . '_' . Str::random(20);

        RefCodePackage::create([
            'user_mgnt_id' => $userMgmt->id, 
            'ref_code' => $refCode,
            'is_official' => $isOfficial,
            'ref_installation_price' => 250000,
            'ref_monthly_price' => 50000,
        ]);

        return $user;
    }
}