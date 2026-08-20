<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Tenants;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TenantSheetImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    protected $createdBy;
    public $importSessionKey;

    public function __construct($createdBy)
    {
        $this->createdBy = $createdBy;
        $this->importSessionKey = 'import_session_' . time() . '.json';
    }

    public function collection(Collection $rows)
    {
        $importedData = [
            'users' => [],
            'tenants' => [],
            'emergency_contacts' => []
        ];

        DB::transaction(function () use ($rows, &$importedData) {
            foreach ($rows as $row) {
                // Skip if essential name is missing
                if (empty($row['full_name'])) {
                    continue;
                }

                // 1. Handle Email Generation (if empty or null)
                $email = $row['email_address'] ?? null;
                if (empty($email)) {
                    $email = 'tenant_' . time() . '_' . Str::random(5) . '@anysio.local';
                }

                // 2. Prepare Tenant details
                $icNumber = !empty(trim($row['ic_number'] ?? '')) ? trim($row['ic_number']) : null;
                $passport = !empty(trim($row['passport_number'] ?? '')) ? trim($row['passport_number']) : null;
                $phone    = !empty(trim($row['phone_number'] ?? '')) ? trim($row['phone_number']) : '-';

                // Check if tenant already exists by matching EITHER IC Number OR Passport Number (scoped by created_by)
                $tenant = null;
                if (!empty($icNumber) || !empty($passport)) {
                    $tenant = Tenants::where('created_by', $this->createdBy)
                        ->where(function ($query) use ($icNumber, $passport) {
                            if (!empty($icNumber)) {
                                $query->orWhere('ic_number', $icNumber);
                            }
                            if (!empty($passport)) {
                                $query->orWhere('passport', $passport);
                            }
                        })
                        ->first();
                }

                if ($tenant) {
                    // UPDATE existing tenant and their associated user account
                    $tenant->update([
                        'ic_number'   => $icNumber ?? $tenant->ic_number,
                        'passport'    => $passport ?? $tenant->passport,
                        'phone'       => $phone,
                        'nationality' => $row['nationality'] ?? $tenant->nationality, 
                        'gender'      => $row['gender'] ?? $tenant->gender,
                        'occupation'  => $row['occupation'] ?? $tenant->occupation,
                    ]);

                    // Update user name if present
                    if ($tenant->user) {
                        $tenant->user->update([
                            'name' => $row['full_name'],
                        ]);
                    }
                } else {
                    // CREATE new User account
                    $user = User::create([
                        'name'     => $row['full_name'],
                        'email'    => $email,
                        'password' => Hash::make(Str::random(10)),
                        'role'     => 'tenant',
                    ]);
                    $importedData['users'][] = $user->id;

                    // CREATE new Tenant record
                    $tenant = Tenants::create([
                        'user_id'     => $user->id,
                        'created_by'  => $this->createdBy,
                        'phone'       => $phone,
                        'ic_number'   => $icNumber,
                        'passport'    => $passport,
                        'nationality' => $row['nationality'] ?? '', 
                        'gender'      => $row['gender'] ?? '',
                        'occupation'  => $row['occupation'] ?? null,
                    ]);
                    $importedData['tenants'][] = $tenant->id;
                }
            }

            // Save session tracking data (Emergency contacts sheet will load and append to this later)
            Storage::disk('local')->put('imports/' . $this->importSessionKey, json_encode($importedData));
        });
    }
}