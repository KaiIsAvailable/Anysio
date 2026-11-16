<?php
namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class UtilityTariffAssignment extends Model
{
    use HasUlids, Auditable;
    protected $guarded = ['id'];
    protected $casts = ['effective_from' => 'date'];
    public function tariff() { return $this->belongsTo(UtilityTariff::class, 'utility_tariff_id'); }
}
