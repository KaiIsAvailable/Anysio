<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class DocumentTemplate extends Model
{
    use HasUlids, SoftDeletes, Auditable;

    protected $table = 'document_templates';

    protected $fillable = [
        'parent_id',
        'user_id',
        'created_by',
        'category',
        'title',
        'version',
        'details',
        'html_template',
        'status',
        'is_system_default',
    ];

    // --- 关联关系 ---

    /**
     * 获取父版本模板 (前一个版本)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'parent_id', 'id');
    }

    /**
     * 获取所有后续子版本
     */
    public function children(): HasMany
    {
        return $this->hasMany(DocumentTemplate::class, 'parent_id', 'id');
    }

    /**
     * 获取同一类目下的所有历史版本
     */
    public function versions(): HasMany
    {
        // 查找所有以当前模板的 parent_id 为根或作为同一谱系的模板
        // 简单的做法是查找所有 category 相同且属于同一 owner 的记录
        return $this->hasMany(DocumentTemplate::class, 'category', 'category')
                    ->where(function ($query) {
                        $query->where('user_id', $this->user_id)
                              ->orWhereNull('user_id');
                    })
                    ->orderBy('version', 'desc');
    }

    /**
     * 关联创建者 (如果是系统管理员创建，user_id 可能为 null，由 created_by 标识)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * 关联归属用户 (如果该模板是某个房东/公司的私有模板)
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    // --- 业务辅助方法 ---

    /**
     * 检查是否为当前最新版本
     */
    public function isLatest(): bool
    {
        return !$this->children()->exists();
    }
}