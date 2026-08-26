<?php
namespace App\Imports;

use App\Models\Owners;
use App\Models\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class OwnerSheetImport implements ToCollection, WithHeadingRow
{
    protected $agentId;
    protected $sessionKey;

    public function __construct($agentId, $sessionKey)
    {
        $this->agentId = $agentId;
        $this->sessionKey = $sessionKey;
    }

    public function collection(Collection $rows)
    {
        $importedOwners = [];
        $importedUsers = [];

        foreach ($rows as $row) {
            if (empty($row['full_name'])) {
                continue;
            }

            // Create a user record for the owner
            $user = User::create([
                'name'     => $row['full_name'],
                'email'    => !empty($row['email_address']) ? $row['email_address'] : Str::random(10) . '@example.com',
                'password' => Hash::make('defaultPassword123'),
                'role'     => 'owner',
            ]);
            $importedUsers[] = $user->id;

            // Create the owner record
            $owner = Owners::create([
                'user_id'      => $user->id,
                'agent_id'     => $this->agentId,
                'company_name' => $row['company_name'] ?? 'N/A',
                'ic_number'    => $row['ic_number'] !== '-' ? ($row['ic_number'] ?? null) : 'N/A',
                'phone'        => $row['phone_number'] ?? 'N/A',
                'gender'       => $row['gender'] ?? 'N?A',
            ]);
            $importedOwners[] = $owner->id;
        }

        // Save session tracking data safely
        $filePath = 'imports/' . $this->sessionKey;
        $sessionData = Storage::disk('local')->exists($filePath) 
            ? json_decode(Storage::disk('local')->get($filePath), true) 
            : [];

        // MERGE instead of overwrite (Example for OwnerSheetImport)
        $sessionData['owners'] = array_merge($sessionData['owners'] ?? [], $importedOwners);
        $sessionData['users'] = array_merge($sessionData['users'] ?? [], $importedUsers);

        Storage::disk('local')->put($filePath, json_encode($sessionData));
    }
}