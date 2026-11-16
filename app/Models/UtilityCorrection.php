<?php
namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class UtilityCorrection extends Model
{
    use HasUlids, Auditable;
    protected $guarded = ['id'];
    protected $casts = ['original_snapshot' => 'array', 'replacement_snapshot' => 'array', 'difference' => 'integer'];
    public function originalItem() { return $this->belongsTo(InvoiceItem::class, 'original_invoice_item_id'); }
    public function replacementItem() { return $this->belongsTo(InvoiceItem::class, 'replacement_invoice_item_id'); }
}
