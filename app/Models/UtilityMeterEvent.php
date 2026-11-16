<?php
namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class UtilityMeterEvent extends Model
{
    use HasUlids, Auditable;
    protected $guarded = ['id'];
    protected $casts = ['event_date' => 'date', 'old_closing' => 'decimal:3', 'new_opening' => 'decimal:3', 'capacity' => 'decimal:3'];
}
