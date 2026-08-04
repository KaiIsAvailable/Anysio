<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // 记得引入 DB

class DocumentTemplateService
{
    public function create(array $data): DocumentTemplate
    {
        return DocumentTemplate::create([
            'parent_id' => null, // Create 永远是始祖
            'user_id' => $data['user_id'] ?: null,
            'created_by' => Auth::id(),
            'category' => $data['category'],
            'title' => $data['title'],
            'version' => $data['version'],
            'details' => $data['details'] ?? '',
            'html_template' => $data['html_template'],
            'status' => 'active',
            'is_system_default' => empty($data['user_id']),
        ]);
    }

    // 💡 新增：专门处理编辑后生成新版本的逻辑
    public function createNewVersion(DocumentTemplate $oldTemplate, array $data): DocumentTemplate
    {
        return DB::transaction(function () use ($oldTemplate, $data) {
            // 1. 找到这个家族的始祖 ID
            $rootParentId = $oldTemplate->parent_id ?: $oldTemplate->id;

            // 2. 将这棵树上的所有旧版本设为 inactive
            DocumentTemplate::where(function ($q) use ($rootParentId) {
                $q->where('id', $rootParentId)
                  ->orWhere('parent_id', $rootParentId);
            })->update(['status' => 'inactive']);

            // 3. 创建新的版本，并绑定 parent_id 认祖归宗
            return DocumentTemplate::create([
                'parent_id' => $rootParentId,
                'user_id' => $data['user_id'] ?: null,
                'created_by' => Auth::id(),
                'category' => $data['category'],
                'title' => $data['title'],
                'version' => $data['version'],
                'details' => $data['details'] ?? '',
                'html_template' => $data['html_template'],
                'status' => 'active', // 新版本自动成为 active
                'is_system_default' => empty($data['user_id']),
            ]);
        });
    }
}