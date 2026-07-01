<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Auth;

class DocumentTemplateService
{
    public function create(array $data): DocumentTemplate
    {
        return DocumentTemplate::create([
            'parent_id' => null,
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
}