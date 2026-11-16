<?php
namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\{Model, Builder};
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class UtilityTariff extends Model
{
    use HasUlids, Auditable;
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];
    public function versions() { return $this->hasMany(UtilityTariffVersion::class); }
    public function scopeVisibleTo(Builder $query, string $manager): Builder
    {
        return $query->where(fn ($q) => $q->where('kind', 'official')->whereNull('management_user_id')->orWhere('management_user_id', $manager));
    }
}
