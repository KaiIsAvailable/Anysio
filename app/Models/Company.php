<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsToMany, HasMany};

class Company extends Model
{
    use HasFactory, HasUlids, Auditable;

    protected $table = 'companies';

    protected $fillable = [
        'name',
        'logo_path',
        'ssm_old_number',
        'ssm_new_number',
        'address',
        'phone',
        'email',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
            ->using(CompanyUser::class)
            ->withTimestamps();
    }

    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'company_id');
    }
}
