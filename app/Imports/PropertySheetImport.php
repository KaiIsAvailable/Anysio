<?php
namespace App\Imports;

use App\Models\Property;
use App\Models\Owners;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Storage;

class PropertySheetImport implements ToCollection, WithHeadingRow
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
        $importedProperties = [];

        foreach ($rows as $row) {
            if (empty($row['property_name']) || empty($row['full_name'])) {
                continue;
            }

            // Find the owner by their full name via the User relationship
            $owner = Owners::whereHas('user', function ($q) use ($row) {
                $q->where('name', $row['full_name']);
            })->first();

            if (!$owner) {
                continue;
            }

            $property = Property::create([
                'created_by'    => $this->agentId,
                'owner_id'      => $owner->id,
                'name'          => $row['property_name'], 
                'type'          => $row['property_type'] ?? 'N/A',
                'address'       => $row['address'] ?? 'N/A',
                'postcode'      => $row['postcode'] ?? 'N/A',
                'city'          => $row['city'] ?? 'N/A',
                'state'         => $row['state'] ?? 'N/A',
            ]);

            $importedProperties[] = $property->id;
        }

        // Save session tracking data safely
        $filePath = 'imports/' . $this->sessionKey;
        $sessionData = Storage::disk('local')->exists($filePath) 
            ? json_decode(Storage::disk('local')->get($filePath), true) 
            : [];

        $sessionData['properties'] = array_merge($sessionData['properties'] ?? [], $importedProperties);

        Storage::disk('local')->put($filePath, json_encode($sessionData));
    }
}