<?php
namespace App\Imports;

use App\Models\User;
use App\Models\Tenants;
use App\Models\EmergencyContact;
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

                // 2. Create the User account for the tenant
                $user = User::create([
                    'name'     => $row['full_name'],
                    'email'    => $email,
                    'password' => Hash::make(Str::random(10)),
                    'role'     => 'tenant',
                ]);
                $importedData['users'][] = $user->id;

                // 3. Prepare Tenant details (IC vs Passport logic)
                $icNumber = !empty(trim($row['ic_number'] ?? '')) ? trim($row['ic_number']) : null;
                $passport = !empty(trim($row['passport_number'] ?? '')) ? trim($row['passport_number']) : null;
                $phone    = !empty(trim($row['phone_number'] ?? '')) ? trim($row['phone_number']) : '-';

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

                // 4. Handle Emergency Contact by linking via Tenant's Full Name
                if (!empty($row['emergency_name']) && !empty($row['tenant_name'])) {
                    // Look up the tenant by matching the user's name (since tenants are linked to users)
                    $targetTenant = Tenants::whereHas('user', function ($query) use ($row) {
                        $query->where('name', $row['tenant_name']);
                    })->first();

                    if ($targetTenant) {
                        $contact = EmergencyContact::create([
                            'tenant_id'    => $targetTenant->id,
                            'name'         => $row['emergency_name'],
                            'phone'        => $row['emergency_phone'] ?? null,
                            'relationship' => $row['relationship'] ?? '',
                        ]);
                        $importedData['emergency_contacts'][] = $contact->id;
                    }
                }
            }

            // Save all created IDs to the tracking JSON file
            Storage::disk('local')->put('imports/' . $this->importSessionKey, json_encode($importedData));
        });
    }
}