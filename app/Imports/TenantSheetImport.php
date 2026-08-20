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
                if (empty($row['name'])) {
                    continue;
                }

                // 1. Handle Email Generation (if empty or null)
                $email = $row['email'] ?? null;
                if (empty($email)) {
                    $email = 'tenant_' . time() . '_' . Str::random(5) . '@anysio.local';
                }

                // 2. Create the User account for the tenant
                $user = User::create([
                    'name'     => $row['name'],
                    'email'    => $email,
                    'password' => Hash::make(Str::random(10)),
                    'role'     => 'tenant',
                ]);
                $importedData['users'][] = $user->id;

                // 3. Prepare Tenant details
                $identityType = $row['identity_type'] ?? 'ic'; // Default to ic if not specified
                $icNumber = $row['ic_number'] ?? null;
                $passport = $row['passport'] ?? null;

                if ($identityType === 'ic') {
                    $passport = null;
                } else {
                    $icNumber = null;
                }

                $tenant = Tenants::create([
                    'user_id'     => $user->id,
                    'created_by'  => $this->createdBy,
                    'phone'       => !empty($row['phone']) ? $row['phone'] : '',
                    'ic_number'   => $icNumber,
                    'passport'    => $passport,
                    'nationality' => $row['nationality'] ?? '', 
                    'gender'      => $row['gender'] ?? '',
                    'occupation'  => $row['occupation'] ?? null,
                ]);
                $importedData['tenants'][] = $tenant->id;

                // 4. Handle Emergency Contact (Supports single column or multiple entries if structured)
                if (!empty($row['emergency_name'])) {
                    $contact = EmergencyContact::create([
                        'tenant_id'    => $tenant->id,
                        'name'         => $row['emergency_name'],
                        'phone'        => $row['emergency_phone'] ?? null,
                        'relationship' => $row['relationship'] ?? '',
                    ]);
                    $importedData['emergency_contacts'][] = $contact->id;
                }
            }

            // Save all created IDs to the tracking JSON file
            Storage::disk('local')->put('imports/' . $this->importSessionKey, json_encode($importedData));
        });
    }
}