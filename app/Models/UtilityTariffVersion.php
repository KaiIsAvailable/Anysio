<?php
namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Validation\ValidationException;

class UtilityTariffVersion extends Model
{
    use HasUlids, Auditable;
    protected $guarded = ['id'];
    protected $casts = ['definition' => 'array', 'effective_from' => 'date', 'verified_at' => 'datetime', 'version' => 'integer'];
    public function tariff() { return $this->belongsTo(UtilityTariff::class, 'utility_tariff_id'); }
    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['tariff' => 'Tariff versions are immutable. Create a new version.']));
        static::deleting(fn () => throw ValidationException::withMessages(['tariff' => 'Tariff versions are retained for billing history.']));
    }
}
