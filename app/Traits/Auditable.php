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

    public static function logError($model, $message, $context = [])
    {
        // 获取关联类名，如果 $model 不存在，则存为 'System' 或 'General'
        $type = $model ? get_class($model) : 'App\Models\System';
        $id = $model ? $model->id : null;

        AuditLog::create([
            'user_id'        => Auth::id(),
            'event'          => 'error', 
            'auditable_type' => $type,
            'auditable_id'   => $id,
            'old_values'     => null,
            // 将错误信息和额外的上下文合并
            'new_values'     => [
                'message' => $message, 
                'details' => $context
            ],
            'ip_address'     => Request::ip(),
            'user_agent'     => Request::userAgent(),
        ]);
    }
}