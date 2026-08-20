<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TenantSheetImport;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenants;
use App\Models\User;
use App\Models\EmergencyContact;
use Illuminate\Support\Facades\DB;

class ExcelImportController extends Controller
{
    public function store(Request $request, $type)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls,csv',
            'created_by' => 'required|exists:users,id',
        ]);

        $tenantImporter = new TenantSheetImport($request->created_by);

        $importers = [
            'tenants' => $tenantImporter,
        ];

        if (!array_key_exists($type, $importers)) {
            return redirect()->back()->with('error', 'Invalid import type specified.');
        }

        try {
            // Use the clean Facade syntax
            Excel::import($importers[$type], $request->file('excel_file'));

            return redirect()->back()
                ->with('success', 'Tenants imported temporarily for review.')
                ->with('import_session', $tenantImporter->importSessionKey);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function revertImport(Request $request)
    {
        $sessionKey = $request->input('session_key');
        $filePath = 'imports/' . $sessionKey;

        if (Storage::disk('local')->exists($filePath)) {
            $data = json_decode(Storage::disk('local')->get($filePath), true);

            DB::transaction(function () use ($data) {
                // Delete Emergency Contacts first
                if (!empty($data['emergency_contacts'])) {
                    EmergencyContact::whereIn('id', $data['emergency_contacts'])->delete();
                }

                // Delete Tenants
                if (!empty($data['tenants'])) {
                    Tenants::whereIn('id', $data['tenants'])->delete();
                }

                // Delete Associated User accounts created during the import
                if (!empty($data['users'])) {
                    User::whereIn('id', $data['users'])->delete();
                }
            });

            Storage::disk('local')->delete($filePath);

            return redirect()->back()->with('success', 'Import successfully reversed and data removed.');
        }

        return redirect()->back()->with('error', 'Import session not found or already processed.');
    }

    // 2. If user clicks "Done"
    public function confirmImport(Request $request)
    {
        $sessionKey = $request->input('session_key');
        $filePath = 'imports/' . $sessionKey;

        if (Storage::disk('local')->exists($filePath)) {
            // Just delete the temporary JSON file, keeping the database data permanent
            Storage::disk('local')->delete($filePath);
            
            return redirect()->back()->with('success', 'Import confirmed and finalized!');
        }

        return redirect()->back()->with('error', 'Import session already expired.');
    }
}