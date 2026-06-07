<?php 
// App\Traits\SyncableStatus.php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait SyncableStatus {
    // 1. 向下传播：将状态强制应用到所有子孙
    public function propagateStatus($status) {
        $relation = method_exists($this, 'childrenItems') ? $this->childrenItems() : null;
        if (!$relation) return;

        // 批量更新子级
        $relation->update(['status' => $status]);
        
        // 递归处理下一层
        foreach ($relation->get() as $child) {
            $child->propagateStatus($status);
        }
    }

    // 2. 向上聚合：根据所有子级的状态，决定父级的状态
    public function syncStatus() 
    {
        $relation = method_exists($this, 'childrenItems') ? $this->childrenItems() : null;
        if (!$relation) return;

        $children = $relation->get();
        if ($children->isEmpty()) return;

        // 1. 定义状态优先级 (数值越大，优先级越高)
        $priority = [
            'Occupied' => 3,
            'Cleaning' => 2,
            'Vacant'   => 1,
        ];

        // 2. 获取所有子级的状态，取优先级最高的那一个
        // 如果子级状态不在列表中，默认为 0
        $newStatus = $children->map(function ($child) use ($priority) {
            return $priority[$child->status] ?? 0;
        })->pipe(function ($priorities) use ($priority) {
            $maxPriority = $priorities->max();
            // 反向查表找到对应的状态名称
            return array_search($maxPriority, $priority) ?: 'Vacant';
        });

        $currentStatus = $this->getAttribute('status');

        if ($currentStatus !== $newStatus) {
            $this->update(['status' => $newStatus]);
            
            // 3. 递归向上
            if (method_exists($this, 'parentItem')) {
                $parent = $this->parentItem;
                if ($parent && method_exists($parent, 'syncStatus')) {
                    $parent->syncStatus();
                }
            }
        }
    }
}