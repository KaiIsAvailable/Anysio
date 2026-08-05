<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\FeeTypeService;

class FeeTypeController extends Controller
{
    protected FeeTypeService $feeTypeService;

    public function __construct(FeeTypeService $feeTypeService)
    {
        $this->feeTypeService = $feeTypeService;
    }

    /**
     * Store a newly created fee type in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|string|in:invoice,expense',
        ]);

        $feeType = $this->feeTypeService->createFeeType($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'feeType' => $feeType
            ]);
        }

        return back()->with('success', 'Fee type added successfully.');
    }
}