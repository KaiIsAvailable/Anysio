<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agreements extends Model
{
    use HasUlids, SoftDeletes;
    protected $table = 'agreements';
    protected $fillable = [
        'parent_agreement_id',
        'owner_id',
        'type',
        'title',
        'version',
        'content',
        'status',
    ];

    public function allHistory()
    {
        $query = $this->hasMany(Agreements::class, 'type', 'type')
                    ->where('id', '!=', $this->id);

        // 如果当前记录有 owner_id，则匹配相同的 owner_id
        if ($this->owner_id) {
            return $query->where('owner_id', $this->owner_id)
                        ->orderBy('version', 'desc');
        }

        // 如果没有 owner_id (如 TOS/Privacy)，则匹配同样没有 owner_id 的记录
        return $query->whereNull('owner_id')
                    ->orderBy('version', 'desc');
    }

    public function historyVersions()
    {
        return $this->hasMany(Agreements::class, 'parent_agreement_id', 'parent_agreement_id')
                    ->where('id', '!=', $this->id); // 排除自己
    }

    // 在 Agreements 模型中
    public function previousVersion()
    {
        // 这是一个一对一的关系，因为一个新版通常只有一个直接前任
        return $this->belongsTo(Agreements::class, 'parent_agreement_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owners::class);
    }
}