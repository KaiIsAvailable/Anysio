<?php
namespace App\Imports;

use App\Models\{Unit, Owners, Property};
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Storage;

class UnitSheetImport implements ToCollection, WithHeadingRow
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
        $importedUnits = [];

        foreach ($rows as $row) {
            // Ensure unit_no and property_name exist
            if (empty($row['unit_no']) || empty($row['property_name'])) {
                continue;
            }

            $property = Property::where('name', $row['property_name'])->first();
            if (!$property) {
                continue;
            }

            // EVERY unit must have an owner. If full_name is missing, skip the row.
            if (empty($row['full_name'])) {
                continue; 
            }

            $owner = Owners::whereHas('user', function ($q) use ($row) {
                $q->where('name', $row['full_name']);
            })->first();

            // If the owner doesn't exist in the database, skip this unit
            if (!$owner) {
                continue; 
            }

            // Clean up sqft: if it's 'N/A' or non-numeric, save as null
            $sqft = $row['size_sqft'] ?? null;
            if (!is_numeric($sqft)) {
                $sqft = null;
            }

            $unit = Unit::create([
                'property_id'        => $property->id,
                'owner_id'           => $owner->user_id, // Uses the correct user_id foreign key
                'created_by'         => $this->agentId,
                'unit_no'            => $row['unit_no'],
                'block'              => $row['block_tower'] ?? null,
                'floor'              => isset($row['floor']) ? (string) $row['floor'] : null,
                'sqft'               => $sqft,
                'electricity_acc_no' => $row['electricity_acc_no'] ?? null,
                'water_acc_no'       => $row['water_acc_no'] ?? null,
                'indah_water_acc_no' => $row['indah_water_acc_no'] ?? null,
            ]);

            $importedUnits[] = $unit->id;
        }

        // Save session tracking data safely
        $filePath = 'imports/' . $this->sessionKey;
        $sessionData = Storage::disk('local')->exists($filePath) 
            ? json_decode(Storage::disk('local')->get($filePath), true) 
            : [];

        $sessionData['units'] = array_merge($sessionData['units'] ?? [], $importedUnits);

        Storage::disk('local')->put($filePath, json_encode($sessionData));
    }
}