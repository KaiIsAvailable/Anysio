<?php
namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * @method static void created(callable $callback)
 * @method static void updated(callable $callback)
 * @method static void deleted(callable $callback)
 */
trait Auditable
{
    public static function bootAuditable()
    {
        // 1. 追踪创建
        static::created(function ($model) {
            static::logActivity($model, 'created', null, $model->toArray());
        });

        // 2. 追踪更新
        static::updated(function ($model) {
            static::logActivity($model, 'updated', $model->getOriginal(), $model->getChanges());
        });

        // 3. 追踪删除
        static::deleted(function ($model) {
            static::logActivity($model, 'deleted', $model->toArray(), null);
        });
    }

    /**
     * 统一处理日志记录逻辑
     */
    protected static function logActivity($model, $event, $old, $new)
    {
        AuditLog::create([
            'user_id'        => Auth::id(),
            'event'          => $event,
            'auditable_type' => get_class($model),
            'auditable_id'   => $model->id,
            'old_values'     => $old,
            'new_values'     => $new,
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
        ]);
    }
}