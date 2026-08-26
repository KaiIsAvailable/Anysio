<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\{TenantSheetImport, EmergencyContactSheetImport, OwnerSheetImport, PropertySheetImport, UnitSheetImport, RoomSheetImport};
use App\Models\{Tenants, User, EmergencyContact, Owners, Property, Unit, Room};
use Illuminate\Support\Facades\Storage;
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
        set_time_limit(120);
        ini_set('max_execution_time', '120');
        
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls,csv',
            'created_by' => 'required|exists:users,id',
        ]);

        if (!in_array($type, ['tenants', 'owners'])) {
            return redirect()->back()->with('error', 'Invalid import type specified.');
        }

        try {
            $sessionKey = 'import_session_' . time() . '.json';
            
            if ($type === 'tenants') {
                $importer = new class($request->created_by, $sessionKey) implements WithMultipleSheets {
                    protected $createdBy;
                    protected $sessionKey;

                    public function __construct($createdBy, $sessionKey)
                    {
                        $this->createdBy = $createdBy;
                        $this->sessionKey = $sessionKey;
                    }

                    public function sheets(): array
                    {
                        return [
                            'Tenant'           => new TenantSheetImport($this->createdBy, $this->sessionKey),
                            'Emergency Number' => new EmergencyContactSheetImport($this->sessionKey),
                        ];
                    }
                };
            } else {
                // For owners import, using the admin's ID directly as the agent/creator
                $agentId = $request->created_by;

                $importer = new class($agentId, $sessionKey) implements WithMultipleSheets {
                    protected $agentId;
                    protected $sessionKey;

                    public function __construct($agentId, $sessionKey)
                    {
                        $this->agentId = $agentId;
                        $this->sessionKey = $sessionKey;
                    }

                    public function sheets(): array
                    {
                        return [
                            'Owner'    => new OwnerSheetImport($this->agentId, $this->sessionKey),
                            'Property' => new PropertySheetImport($this->agentId, $this->sessionKey),
                            'Unit'     => new UnitSheetImport($this->agentId, $this->sessionKey),
                            'Room'     => new RoomSheetImport($this->agentId, $this->sessionKey),
                        ];
                    }
                };
            }

            Excel::import($importer, $request->file('excel_file'));
            
            session()->put('import_session', $sessionKey);

            return redirect()->back()->with('success', ucfirst($type) . ' imported temporarily for review.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error importing file: ' . $e->getMessage());
        }
    }

    public function revertImport(Request $request)
    {
        // Fallback to session if request input is empty
        $sessionKey = $request->input('session_key') ?? session('import_session');
        $filePath = 'imports/' . $sessionKey;

        if ($sessionKey && Storage::disk('local')->exists($filePath)) {
            $data = json_decode(Storage::disk('local')->get($filePath), true);

            DB::transaction(function () use ($data) {
                // 1. Delete Rooms first (deepest child level)
                if (!empty($data['rooms'])) {
                    Room::whereIn('id', $data['rooms'])->delete();
                }

                // 2. Delete Units
                if (!empty($data['units'])) {
                    Unit::whereIn('id', $data['units'])->delete();
                }

                // 3. Delete Properties
                if (!empty($data['properties'])) {
                    Property::whereIn('id', $data['properties'])->delete();
                }

                // 4. Delete Owners
                if (!empty($data['owners'])) {
                    Owners::whereIn('id', $data['owners'])->delete();
                }

                // 5. Delete Emergency Contacts (for tenant imports)
                if (!empty($data['emergency_contacts'])) {
                    EmergencyContact::whereIn('id', $data['emergency_contacts'])->delete();
                }

                // 6. Delete Tenants (for tenant imports)
                if (!empty($data['tenants'])) {
                    Tenants::whereIn('id', $data['tenants'])->delete();
                }

                // 7. Delete Associated User accounts created during either import
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
        // Fallback to session if request input is empty
        $sessionKey = $request->input('session_key') ?? session('import_session');
        $filePath = 'imports/' . $sessionKey;

        if ($sessionKey && Storage::disk('local')->exists($filePath)) {
            // Just delete the temporary JSON file, keeping the database data permanent
            Storage::disk('local')->delete($filePath);
            session()->forget('import_session');
            
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

    public function downloadOwnerTemplate()
    {
        return Excel::download(new class implements WithMultipleSheets {
            public function sheets(): array
            {
                return [
                    // Sheet 1: Owner Information
                    new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
                        public function collection() { return collect([]); }

                        public function headings(): array
                        {
                            return ['Full Name', 'Email Address', 'Company Name', 'IC Number', 'Phone Number', 'Gender'];
                        }

                        public function title(): string { return 'Owner'; }

                        public function registerEvents(): array
                        {
                            return [
                                AfterSheet::class => function(AfterSheet $event) {
                                    $sheet = $event->sheet->getDelegate();
                                    $headerStyle = [
                                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1E293B']],
                                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                                    ];
                                    $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);
                                    $sheet->getRowDimension(1)->setRowHeight(25);
                                },
                            ];
                        }
                    },

                    // Sheet 2: Property Information (Dropdown for Owner Full Name)
                    new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
                        public function collection() { return collect([]); }

                        public function headings(): array
                        {
                            return ['Property Name', 'Owner Full Name', 'Property Type', 'Address', 'Postcode', 'City', 'State'];
                        }

                        public function title(): string { return 'Property'; }

                        public function registerEvents(): array
                        {
                            return [
                                AfterSheet::class => function(AfterSheet $event) {
                                    $sheet = $event->sheet->getDelegate();
                                    $headerStyle = [
                                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1E293B']],
                                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                                    ];
                                    $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
                                    $sheet->getRowDimension(1)->setRowHeight(25);

                                    // Dropdown for Owner Name from Sheet 1 (Column B)
                                    for ($row = 2; $row <= 100; $row++) {
                                        $validation = $sheet->getCell("B{$row}")->getDataValidation();
                                        $validation->setType(DataValidation::TYPE_LIST);
                                        $validation->setErrorStyle(DataValidation::STYLE_STOP);
                                        $validation->setAllowBlank(true);
                                        $validation->setShowInputMessage(true);
                                        $validation->setPromptTitle('Select Owner');
                                        $validation->setPrompt('Select an owner from the Owner sheet.');
                                        $validation->setShowErrorMessage(true);
                                        $validation->setErrorTitle('Invalid Selection');
                                        $validation->setError('You must select a valid owner name from the Owner sheet.');
                                        $validation->setShowDropDown(true);
                                        $validation->setFormula1("Owner!\$A\$2:\$A\$100");
                                    }
                                },
                            ];
                        }
                    },

                    // Sheet 3: Unit Information (Dropdown for Property Name)
                    new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
                        public function collection() { return collect([]); }

                        public function headings(): array
                        {
                            return ['Unit No', 'Property Name', 'Block / Tower', 'Floor', 'Size (Sqft)', 'Electricity Acc No', 'Water Acc No', 'Indah Water Acc No'];
                        }

                        public function title(): string { return 'Unit'; }

                        public function registerEvents(): array
                        {
                            return [
                                AfterSheet::class => function(AfterSheet $event) {
                                    $sheet = $event->sheet->getDelegate();
                                    $headerStyle = [
                                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1E293B']],
                                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                                    ];
                                    $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
                                    $sheet->getRowDimension(1)->setRowHeight(25);

                                    // Dropdown for Property Name from Sheet 2 (Column A)
                                    for ($row = 2; $row <= 100; $row++) {
                                        $validation = $sheet->getCell("B{$row}")->getDataValidation();
                                        $validation->setType(DataValidation::TYPE_LIST);
                                        $validation->setErrorStyle(DataValidation::STYLE_STOP);
                                        $validation->setAllowBlank(true);
                                        $validation->setShowInputMessage(true);
                                        $validation->setPromptTitle('Select Property');
                                        $validation->setPrompt('Select a property from the Property sheet.');
                                        $validation->setShowErrorMessage(true);
                                        $validation->setErrorTitle('Invalid Selection');
                                        $validation->setError('You must select a valid property name from the Property sheet.');
                                        $validation->setShowDropDown(true);
                                        $validation->setFormula1("Property!\$A\$2:\$A\$100");
                                    }
                                },
                            ];
                        }
                    },

                    // Sheet 4: Room Information (Dropdown for Unit No)
                    new class implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize, WithEvents {
                        public function collection() { return collect([]); }

                        public function headings(): array
                        {
                            return ['Unit No', 'Room Number', 'Room Type'];
                        }

                        public function title(): string { return 'Room'; }

                        public function registerEvents(): array
                        {
                            return [
                                AfterSheet::class => function(AfterSheet $event) {
                                    $sheet = $event->sheet->getDelegate();
                                    $headerStyle = [
                                        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFF']],
                                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => '1E293B']],
                                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
                                    ];
                                    $sheet->getStyle('A1:C1')->applyFromArray($headerStyle);
                                    $sheet->getRowDimension(1)->setRowHeight(25);

                                    // Dropdown for Unit No from Sheet 3 (Column A)
                                    for ($row = 2; $row <= 100; $row++) {
                                        $validation = $sheet->getCell("A{$row}")->getDataValidation();
                                        $validation->setType(DataValidation::TYPE_LIST);
                                        $validation->setErrorStyle(DataValidation::STYLE_STOP);
                                        $validation->setAllowBlank(true);
                                        $validation->setShowInputMessage(true);
                                        $validation->setPromptTitle('Select Unit');
                                        $validation->setPrompt('Select a unit from the Unit sheet.');
                                        $validation->setShowErrorMessage(true);
                                        $validation->setErrorTitle('Invalid Selection');
                                        $validation->setError('You must select a valid unit no from the Unit sheet.');
                                        $validation->setShowDropDown(true);
                                        $validation->setFormula1("Unit!\$A\$2:\$A\$100");
                                    }
                                },
                            ];
                        }
                    },
                ];
            }
        }, 'owner_import_template.xlsx');
    }
}