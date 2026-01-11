<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserManagement;
use App\Models\Owners;
use App\Models\Room;
use App\Models\RoomAsset;
use App\Models\Utility;
use App\Models\Tenants;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Maintenance;
use App\Models\Notification;
use App\Models\NotificationRecipient;
use App\Models\EmergencyContact;
use App\Models\Ticket;
use App\Models\TicketMsg;
use App\Models\RefCodePackage; // 确保引入此模型
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
            // Admin 通常是官方代码的发起者
            $adminUser = $this->createPmsUserWithRefCode(
                'System Admin', 
                'admin@anysio.com', 
                'admin', 
                null, // 无推荐人
                true  // is_official = true
            );

            // --- 2. 高级房东 (Owner Admin) ---
            $ownerAdminUser = $this->createPmsUserWithRefCode(
                'Main Owner Boss', 
                'owneradmin@anysio.com', 
                'ownerAdmin', 
                $adminUser->id
            );

            // 补充 Owner Admin 的 Profile 资料
            Owners::create([
                'user_id' => $ownerAdminUser->id,
                'company_name' => 'Anysio Real Estate',
                'ic_number' => '880101-14-1111',
                'phone' => '0121112222',
                'gender' => 'male',
            ]);

            // --- 3. 中介管理员 (Agent Admin) ---
            $agentAdminUser = $this->createPmsUserWithRefCode(
                'Senior Agent Manager', 
                'agentadmin@anysio.com', 
                'agentAdmin', 
                $ownerAdminUser->id
            );

            // --- 4. 普通房东 (Owner) 循环 ---
            $ownersData = [
                ['name' => 'Michael Scott', 'email' => 'michael@anysio.com', 'company' => 'Dunder Mifflin'],
                ['name' => 'Harvey Specter', 'email' => 'harvey@anysio.com', 'company' => 'Specter Realty'],
                ['name' => 'Bruce Wayne', 'email' => 'bruce@anysio.com', 'company' => 'Wayne Ent'],
                ['name' => 'Tony Stark', 'email' => 'tony@anysio.com', 'company' => 'Stark Tower'],
                ['name' => 'Wanda Maximoff', 'email' => 'wanda@anysio.com', 'company' => null],
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

                // 房产和房间生成逻辑 (保持原样)
                for ($r = 1; $r <= 2; $r++) {
                    $room = Room::create([
                        'owner_id' => $ownerProfile->id,
                        'room_no' => 'A-' . ($index + 1) . '-0' . $r,
                        'room_type' => ($r == 1) ? 'Master Room' : 'Single Room',
                        'status' => 'Available',
                        'address' => 'No ' . ($index + 1) . ', Jalan Anysio, 50450 Kuala Lumpur',
                    ]);

                    RoomAsset::create([
                        'room_id' => $room->id,
                        'name' => 'Air Conditioner',
                        'condition' => 'Good',
                        'last_maintenance' => now()->subMonths(2),
                        'remark' => 'Daikin 1.0HP',
                    ]);
                }
            }

            // --- 5. 普通租客 (Tenant) ---
            // 注意：租客不需要 Ref Code，也不在 UserManagement 表
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
                ]);

                $tenantProfile = Tenants::create([
                    'user_id' => $user->id,
                    'phone' => '01' . rand(10000000, 99999999),
                    'nationality' => 'Malaysian',
                    'gender' => ($index % 2 == 0) ? 'male' : 'female',
                ]);

                EmergencyContact::create([
                    'tenant_id' => $tenantProfile->id,
                    'phone' => '01' . rand(10000000, 99999999),
                    'relationship' => 'Parent',
                ]);
            }

            // --- 6-9. 租约、账单、维修、通知逻辑 (保持你之前的逻辑即可) ---
            // ... [此处省略你原有的 Lease, Payment, Maintenance 循环代码以节省篇幅] ...

        });
    }

    /**
     * 辅助函数：创建 PMS 用户并自动生成推荐码
     */
    private function createPmsUserWithRefCode($name, $email, $role, $referredBy = null, $isOfficial = false)
    {
        // 1. 先创建基础 User
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => $role,
        ]);

        // 2. 创建 UserManagement 记录 (注意：这里返回的是 UserManagement 对象)
        $userMgmt = UserManagement::create([
            'user_id' => $user->id,
            'referred_by' => $referredBy,
            'role' => $role,
            'subscription_status' => 'active',
        ]);

        // 3. 生成唯一的 Ref Code
        $formattedName = Str::upper(str_replace(' ', '_', $name));
        $refCode = $formattedName . '_' . Str::random(20);

        // 4. 写入 RefCodePackage
        // 修正重点：user_mgnt_id 必须指向 $userMgmt->id (user_management 表的主键)
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