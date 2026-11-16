<?php
namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class UtilityAllocation extends Model
{
    use HasUlids, Auditable;
    protected $guarded = ['id'];
    protected $casts = ['snapshot' => 'array', 'amount' => 'integer', 'weight' => 'integer', 'revision' => 'integer',
        'from_date' => 'date', 'to_date' => 'date', 'superseded_at' => 'datetime'];
    public function lease() { return $this->belongsTo(Lease::class); }
    public function invoiceItem() { return $this->belongsTo(InvoiceItem::class); }
    public function reading() { return $this->belongsTo(Utility::class, 'utility_id'); }
}
