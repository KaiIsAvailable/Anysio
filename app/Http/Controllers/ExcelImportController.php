<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\TenantSheetImport;
use Illuminate\Support\Facades\Storage;
use App\Models\Tenants;
use App\Models\User;
use App\Models\EmergencyContact;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

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
            session()->put('import_session', $tenantImporter->importSessionKey);

            return redirect()->back()->with('success', 'Tenants imported temporarily for review.');

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

            session()->forget('import_session');
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

        session()->forget('import_session');
        return redirect()->back()->with('error', 'Import session already expired.');
    }

    public function downloadTemplate()
    {
        return Excel::download(new class implements WithMultipleSheets {
            public function sheets(): array
            {
                return [
                    // Sheet 1: Tenant Information
                    new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
                        public function collection()
                        {
                            return collect([]); // Clean, empty template rows
                        }

                        public function headings(): array
                        {
                            return [
                                'Full Name',
                                'Email Address',
                                'Phone Number',
                                'IC Number',
                                'Passport Number',
                                'Nationality',
                                'Gender',
                                'Occupation',
                            ];
                        }

                        public function title(): string
                        {
                            return 'Tenant';
                        }

                        public function registerEvents(): array
                        {
                            return [
                                AfterSheet::class => function(AfterSheet $event) {
                                    $sheet = $event->sheet->getDelegate();
                                    
                                    // Style the header row (Professional Dark Slate theme)
                                    $headerStyle = [
                                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                                        'fill' => [
                                            'fillType' => Fill::FILL_SOLID,
                                            'startColor' => ['argb' => '1E293B'] // Tailwind Slate-800
                                        ],
                                        'alignment' => [
                                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                                            'vertical' => Alignment::VERTICAL_CENTER,
                                        ]
                                    ];
                                    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
                                    $sheet->getRowDimension(1)->setRowHeight(25);
                                },
                            ];
                        }
                    },

                    // Sheet 2: Emergency Contact Information with Dynamic Dropdown
                    new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
                        public function collection()
                        {
                            return collect([]);
                        }

                        public function headings(): array
                        {
                            return [
                                'Tenant Name',
                                'Emergency Name',
                                'Relationship',
                                'Phone Number',
                            ];
                        }

                        public function title(): string
                        {
                            return 'Emergency Number';
                        }

                        public function registerEvents(): array
                        {
                            return [
                                AfterSheet::class => function(AfterSheet $event) {
                                    $sheet = $event->sheet->getDelegate();

                                    // Style the header row
                                    $headerStyle = [
                                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                                        'fill' => [
                                            'fillType' => Fill::FILL_SOLID,
                                            'startColor' => ['argb' => '1E293B']
                                        ],
                                        'alignment' => [
                                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                                            'vertical' => Alignment::VERTICAL_CENTER,
                                        ]
                                    ];
                                    $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
                                    $sheet->getRowDimension(1)->setRowHeight(25);

                                    // Add Dynamic Dropdown Validation for Column A (Rows 2 to 100)
                                    // This dynamically pulls available tenant names from the 'Tenant' sheet column A
                                    for ($row = 2; $row <= 100; $row++) {
                                        $validation = $sheet->getCell("A{$row}")->getDataValidation();
                                        $validation->setType(DataValidation::TYPE_LIST);
                                        $validation->setErrorStyle(DataValidation::STYLE_STOP);
                                        $validation->setAllowBlank(true);
                                        $validation->setShowInputMessage(true);
                                        $validation->setPromptTitle('Select Tenant');
                                        $validation->setPrompt('Please select a tenant name from the dropdown list.');
                                        $validation->setShowErrorMessage(true);
                                        $validation->setErrorTitle('Invalid Selection');
                                        $validation->setError('You must select a valid tenant name from the Tenant sheet.');
                                        $validation->setShowDropDown(true);
                                        // Formula linking to Sheet 1 column A names
                                        $validation->setFormula1("Tenant!\$A\$2:\$A\$100");
                                    }
                                },
                            ];
                        }
                    },
                ];
            }
        }, 'tenant_import_template.xlsx');
    }
}