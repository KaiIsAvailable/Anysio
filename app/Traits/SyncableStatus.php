<?php 
// App\Traits\SyncableStatus.php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait SyncableStatus
{
    // 防止递归死循环的静态锁
    protected static $isSyncing = false;

    public function syncStatus()
    {
        if (self::$isSyncing) return;
        self::$isSyncing = true;

        try {
            // 1. 强制刷新关联数据：清除缓存并重新加载
            if ($this->relationLoaded('units')) $this->unsetRelation('units');
            if ($this->relationLoaded('rooms')) $this->unsetRelation('rooms');
            
            // 2. 统一获取子项
            $children = method_exists($this, 'childrenItems') ? $this->childrenItems() : null;

            if ($children === null || $children->isEmpty()) {
                // 如果是 Room 或空 Unit，这里设为 Vacant 可能会覆盖你手动设置的 Occupied
                // 建议：仅在没有子级且当前状态为空时才设为默认，否则保持原样
                if (empty($this->status)) {
                    $this->update(['status' => 'Vacant']);
                }
            } else {
                // 核心状态计算逻辑
                if ($children->contains('status', 'Vacant')) {
                    $newStatus = 'Vacant';
                } elseif ($children->contains('status', 'Cleaning')) {
                    $newStatus = 'Cleaning';
                } else {
                    $newStatus = 'Occupied';
                }

                $currentStatus = $this->status ?? $this->getAttribute('status');

                // 只有状态确实发生变化时才更新
                if ($currentStatus !== $newStatus) {
                    $this->update(['status' => $newStatus]);
                    Log::info("模型 " . get_class($this) . " ID: " . $this->getKey() . " 状态更新为: " . $newStatus);
                }
            }

            // 3. 递归向上
            if (method_exists($this, 'parentItem') && $this->parentItem()) {
                $this->parentItem()->syncStatus();
            }
        } finally {
            // 无论是否报错，必须释放锁
            self::$isSyncing = false;
        }
    }
}