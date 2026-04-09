<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserManagement;
use App\Models\Owners;
use App\Models\Room;
use App\Models\Asset; // 必须引入新的 Asset 模型
use App\Models\Tenants;
use App\Models\Property;
use App\Models\Unit;
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

            // --- 4. 房东、物业、单位及房间循环生成 ---
            $ownersData = [
                ['name' => 'Michael Scott', 'email' => 'michael@anysio.com', 'company' => 'Dunder Mifflin'],
                ['name' => 'Harvey Specter', 'email' => 'harvey@anysio.com', 'company' => 'Specter Realty'],
                ['name' => 'Bruce Wayne', 'email' => 'bruce@anysio.com', 'company' => 'Wayne Ent'],
            ];

            foreach ($ownersData as $index => $data) {
                // 创建用户和管理记录
                $user = $this->createPmsUserWithRefCode(
                    $data['name'], 
                    $data['email'], 
                    'owner', 
                    $ownerAdminUser->id
                );

                // 创建 Owner Profile
                $ownerProfile = Owners::create([
                    'user_id' => $user->id,
                    'company_name' => $data['company'],
                    'ic_number' => '70010' . ($index + 1) . '-10-' . rand(1000, 9999),
                    'phone' => '01' . rand(10000000, 99999999),
                    'gender' => ($index % 2 == 0) ? 'male' : 'female',
                ]);

                // --- 第一层：Property (大楼/小区) ---
                $property = Property::create([
                    'name' => ($index % 2 == 0) ? 'PV15 Platinum Victory' : 'SS2 Landed House',
                    'address' => 'No ' . rand(1, 100) . ', Jalan Genting Klang, Setapak',
                    'city' => 'Kuala Lumpur',
                    'postcode' => '53300',
                    'state' => 'WPKL',
                    'type' => ($index % 2 == 0) ? 'Condo' : 'Landed',
                ]);

                // --- 第二层：Unit (产权单位) ---
                for ($u = 1; $u <= 2; $u++) {
                    $unit = Unit::create([
                        'property_id' => $property->id,
                        'owner_id' => $ownerProfile->id, // 明确 Unit 属于哪个 Owner
                        'unit_no' => ($property->type == 'Condo') ? 'A-' . ($index + 10) . '-0' . $u : 'No ' . ($u + 5),
                        'block' => ($property->type == 'Condo') ? 'Block A' : null,
                        'floor' => ($property->type == 'Condo') ? 10 + $index : 1,
                        'sqft' => rand(800, 1300),
                        'status' => 'Vacant',
                    ]);

                    // --- 第三层：Room (租赁房间) ---
                    // 每个 Unit 生成 3 个房间 (Master, Medium, Small)
                    $roomTypes = ['Master Room', 'Medium Room', 'Small Room'];
                    foreach ($roomTypes as $rIndex => $type) {
                        $room = Room::create([
                            'unit_id' => $unit->id, // 关联到 Unit
                            'room_no' => $unit->unit_no . '-' . ($rIndex + 1),
                            'room_type' => $type,
                            'status' => 'Vacant',
                        ]);

                        // --- 重点：关联资产 ---
                        // 随机分配 2-3 个预设资产
                        $randomAssets = collect($assets)->random(rand(2, 3));
                        foreach ($randomAssets as $asset) {
                            $room->assets()->attach($asset->id, [
                                'id' => (string) Str::ulid(), // 必须手动生成中间表 ULID 主键
                                'condition' => 'Good',
                                'last_maintenance' => now()->subMonths(rand(1, 6)),
                                'remark' => 'Standard Item',
                                'quantity' => 1,
                            ]);
                        }
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