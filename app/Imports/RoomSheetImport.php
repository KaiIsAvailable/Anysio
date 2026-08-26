<?php
namespace App\Imports;

use App\Models\Room;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Storage;

class RoomSheetImport implements ToCollection, WithHeadingRow
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
        $importedRooms = [];

        foreach ($rows as $row) {
            if (empty($row['unit_no']) || empty($row['room_number'])) {
                continue;
            }

            $unit = Unit::where('unit_no', $row['unit_no'])->first();

            if (!$unit) {
                continue;
            }

            $room = Room::create([
                'unit_id'     => $unit->id,
                'created_by'  => $this->agentId,
                'room_no'     => $row['room_number'],
                'room_type'   => $row['room_type'] ?? 'N/A',
            ]);

            $importedRooms[] = $room->id;
        }

        // Save session tracking data safely
        $filePath = 'imports/' . $this->sessionKey;
        $sessionData = Storage::disk('local')->exists($filePath) 
            ? json_decode(Storage::disk('local')->get($filePath), true) 
            : [];

        $sessionData['rooms'] = array_merge($sessionData['rooms'] ?? [], $importedRooms);

        Storage::disk('local')->put($filePath, json_encode($sessionData));
    }
}