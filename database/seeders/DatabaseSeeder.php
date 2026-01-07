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
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            
            // 1. 系统管理员 (Admin)
            User::create([
                'name' => 'System Admin',
                'email' => 'admin@anysio.com',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]);

            // 2. 高级房东 (Owner Admin)
            $ownerAdminUser = User::create([
                'name' => 'Main Owner Boss',
                'email' => 'owneradmin@anysio.com',
                'password' => Hash::make('password123'),
                'role' => 'ownerAdmin',
            ]);

            UserManagement::create([
                'user_id' => $ownerAdminUser->id,
                'referred_by' => $ownerAdminUser->id,
                'role' => 'ownerAdmin',
                'subscription_status' => 'active',
            ]);

            Owners::create([
                'user_id' => $ownerAdminUser->id,
                'company_name' => 'Anysio Real Estate',
                'ic_number' => '880101-14-1111',
                'phone' => '0121112222',
                'gender' => 'male',
            ]);

            // 3. 中介管理员 (Agent Admin)
            $agentAdminUser = User::create([
                'name' => 'Senior Agent Manager',
                'email' => 'agentadmin@anysio.com',
                'password' => Hash::make('password123'),
                'role' => 'agentAdmin',
            ]);

            UserManagement::create([
                'user_id' => $agentAdminUser->id,
                'referred_by' => $ownerAdminUser->id,
                'role' => 'agentAdmin',
                'subscription_status' => 'active',
            ]);

            // 4. 普通房东 (Owner) 循环
            $ownersData = [
                ['name' => 'Michael Scott', 'email' => 'michael@anysio.com', 'company' => 'Dunder Mifflin'],
                ['name' => 'Harvey Specter', 'email' => 'harvey@anysio.com', 'company' => 'Specter Realty'],
                ['name' => 'Bruce Wayne', 'email' => 'bruce@anysio.com', 'company' => 'Wayne Ent'],
                ['name' => 'Tony Stark', 'email' => 'tony@anysio.com', 'company' => 'Stark Tower'],
                ['name' => 'Wanda Maximoff', 'email' => 'wanda@anysio.com', 'company' => null],
            ];

            foreach ($ownersData as $index => $data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password123'),
                    'role' => 'owner',
                ]);

                UserManagement::create([
                    'user_id' => $user->id,
                    'referred_by' => $user->id,
                    'role' => 'owner',
                    'subscription_status' => 'active',
                ]);

                $ownerProfile = Owners::create([
                    'user_id' => $user->id,
                    'company_name' => $data['company'],
                    'ic_number' => '70010' . ($index + 1) . '-10-' . rand(1000, 9999),
                    'phone' => '01' . rand(10000000, 99999999),
                    'gender' => ($index % 2 == 0) ? 'male' : 'female',
                ]);

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

                    RoomAsset::create([
                        'room_id' => $room->id,
                        'name' => 'Bed Frame',
                        'condition' => 'New',
                        'last_maintenance' => null,
                        'remark' => 'Queen Size Wood',
                    ]);
                }
            }

            // 5. 普通租客 (Tenant) 循环
            $tenantsList = [
                ['name' => 'John Doe', 'email' => 'john@anysio.com'],
                ['name' => 'Siti Nurhaliza', 'email' => 'siti@anysio.com'],
                ['name' => 'Tan Wei Meng', 'email' => 'weimeng@anysio.com'],
                ['name' => 'Ganesan Perumal', 'email' => 'ganesan@anysio.com'],
                ['name' => 'Sarah Connor', 'email' => 'sarah@anysio.com'],
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

                EmergencyContact::create([
                    'tenant_id' => $tenantProfile->id,
                    'phone' => '01' . rand(10000000, 99999999),
                    'relationship' => 'Friend',
                ]);
            }

            // 6. 租约匹配
            $allRooms = Room::all();
            $allTenants = Tenants::all();

            foreach ($allTenants as $index => $tenant) {
                if (isset($allRooms[$index])) {
                    $room = $allRooms[$index];

                    Lease::create([
                        'room_id'          => $room->id,
                        'tenant_id'        => $tenant->id,
                        'start_date'       => now()->startOfMonth(),
                        'end_date'         => now()->startOfMonth()->addYear(),
                        'monthly_rent'     => ($room->room_type == 'Master Room') ? 800 : 500,
                        'security_deposit' => ($room->room_type == 'Master Room') ? 1600 : 1000,
                        'utilities_depost' => 300, // 注意：如果数据库已改名，请同步修改这里
                        'status'           => 'Active',
                    ]);

                    $room->update(['status' => 'Occupied']);
                }
            }

            // 7. 水电费 & 账单生成
            $allLeases = Lease::with(['room.owner', 'tenant'])->get();

            foreach ($allLeases as $index => $lease) {
                // 生成 Utility
                $prevElectric = rand(1000, 2000);
                $currElectric = $prevElectric + rand(50, 200);
                
                Utility::create([
                    'lease_id'     => $lease->id,
                    'type'         => 'Electricity',
                    'prev_reading' => $prevElectric,
                    'curr_reading' => $currElectric,
                    'amount'       => ($currElectric - $prevElectric) * 0.5,
                ]);

                // 生成 Payment (房租已交)
                Payment::create([
                    'tenant_id'    => $lease->tenant_id,
                    'payment_type' => 'Monthly Rent',
                    'amount_due'   => $lease->monthly_rent,
                    'amount_paid'  => $lease->monthly_rent,
                    'receipt_path' => 'receipts/rent_jan.pdf',
                    'status'       => 'approved',
                    'approved_by'  => $lease->room->owner->id,
                    'created_at'   => now()->subDays(5),
                ]);

                // 生成待交水电账单
                $u = Utility::where('lease_id', $lease->id)->first();
                Payment::create([
                    'tenant_id'    => $lease->tenant_id,
                    'payment_type' => 'Utility: ' . $u->type,
                    'amount_due'   => $u->amount,
                    'status'       => 'pending',
                ]);
            }

            // 8. 维修记录 (修正关联名为 assets)
            $maintenanceLeases = Lease::with('room.assets')->take(3)->get();

            foreach ($maintenanceLeases as $lease) {
                // 基础维修
                Maintenance::create([
                    'lease_id' => $lease->id,
                    'title'    => 'Leaking Pipe',
                    'desc'     => 'The bathroom pipe is leaking.',
                    'status'   => 'Completed',
                    'cost'     => 150,
                    'paid_by'  => 'Owner',
                    'created_at' => now()->subDays(10),
                ]);

                // 资产报修 (使用 assets 方法)
                $asset = $lease->room->assets->where('name', 'Air Conditioner')->first();
                if ($asset) {
                    Maintenance::create([
                        'lease_id' => $lease->id,
                        'asset_id' => $asset->id,
                        'title'    => 'AC Not Cooling',
                        'desc'     => 'Not cold.',
                        'status'   => 'Pending',
                        'cost'     => 0,
                        'paid_by'  => 'Tenant',
                        'created_at' => now()->subDay(),
                    ]);
                }
            }

            // 9. 通知 & 工单 (保持原逻辑)
            $owner = Owners::first();
            if ($owner) {
                $announcement = Notification::create([
                    'owner_id' => $owner->id,
                    'type'     => 'Announcement',
                    'title'    => 'Maintenance Notice',
                    'content'  => 'Water interruption on 15th Jan.',
                ]);

                foreach (Tenants::all() as $t) {
                    NotificationRecipient::create([
                        'notification_id' => $announcement->id,
                        'tenant_id'       => $t->id,
                        'is_read'         => false,
                    ]);
                }
            }

            $sysAdmin = User::where('role', 'admin')->first();
            $ownerAdmin = User::where('role', 'ownerAdmin')->first();
            if ($sysAdmin && $ownerAdmin) {
                $ticket = Ticket::create([
                    'sender_id'  => $ownerAdmin->id,
                    'receive_id' => $sysAdmin->id,
                    'category'   => 'Billing',
                    'subject'    => 'Payment discrepancy',
                    'status'     => 'Processing',
                ]);

                TicketMsg::create([
                    'ticket_id'   => $ticket->id,
                    'sender_type' => 'ownerAdmin',
                    'message'     => 'Check my extra charges please.',
                ]);
            }
        });
    }
}