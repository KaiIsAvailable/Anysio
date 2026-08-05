<?php
namespace App\Services;

use App\Models\FeeType;
use App\FeeTypeCategory;
use Illuminate\Support\Facades\Auth;

class FeeTypeService
{
    /**
     * Get active invoice fee types for the authenticated user.
     */
    public function getActiveInvoiceFeeTypes()
    {
        return FeeType::where('user_id', Auth::id())
            ->where('category', 'invoice')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Create a new fee type.
     */
    public function createFeeType(array $data): FeeType
    {
        // Fallback to INVOICE enum value if category is not provided or invalid
        $category = FeeTypeCategory::tryFrom($data['category'] ?? '') ?? FeeTypeCategory::INVOICE;
        return FeeType::create([
            'user_id' => Auth::id(),
            'name' => $data['name'],
            'category' => $category->value,
            'is_system' => 0,
            'is_active' => true,
        ]);
    }
}