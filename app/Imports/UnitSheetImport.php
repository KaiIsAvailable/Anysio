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
            if (empty($row['unit_no']) || empty($row['property_name'])) {
                continue;
            }

            $property = Property::where('name', $row['property_name'])->first();

            if (!$property) continue;

            $owner = Owners::whereHas('user', function ($q) use ($row) {
                $q->where('name', $row['full_name']);
            })->first();

            if (!$owner) continue;

            $unit = Unit::create([
                'property_id'        => $property->id,
                'owner_id'           => $owner->id,
                'created_by'         => $this->agentId,
                'unit_no'            => $row['unit_no'],
                'block'              => $row['block_tower'] ?? 'N/A',
                'floor'              => $row['floor'] ?? 'N/A',
                'sqft'               => $row['size_sqft'] ?? 'N/A',
                'electricity_acc_no' => $row['electricity_acc_no'] ?? 'N/A',
                'water_acc_no'       => $row['water_acc_no'] ?? 'N/A',
                'indah_water_acc_no' => $row['indah_water_acc_no'] ?? 'N/A',
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