<?php

return [
    'max_files' => 10,
    'max_total_kb' => 102400,
    'max_text_length' => 20000,
    'max_items' => 50,
    'closed_statuses' => ['done', 'complete', 'archive', 'closed'],
    'types' => [
        'jpg' => ['max_kb' => 10240, 'mimes' => ['image/jpeg']],
        'jpeg' => ['max_kb' => 10240, 'mimes' => ['image/jpeg']],
        'png' => ['max_kb' => 10240, 'mimes' => ['image/png']],
        'gif' => ['max_kb' => 10240, 'mimes' => ['image/gif']],
        'webp' => ['max_kb' => 10240, 'mimes' => ['image/webp']],
        'mp4' => ['max_kb' => 51200, 'mimes' => ['video/mp4']],
        'webm' => ['max_kb' => 51200, 'mimes' => ['video/webm']],
        'mov' => ['max_kb' => 51200, 'mimes' => ['video/quicktime']],
        'pdf' => ['max_kb' => 10240, 'mimes' => ['application/pdf']],
        'doc' => ['max_kb' => 10240, 'mimes' => ['application/msword', 'application/vnd.ms-office']],
        'docx' => ['max_kb' => 10240, 'mimes' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']],
        'xls' => ['max_kb' => 10240, 'mimes' => ['application/vnd.ms-excel', 'application/vnd.ms-office']],
        'xlsx' => ['max_kb' => 10240, 'mimes' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']],
        'ppt' => ['max_kb' => 10240, 'mimes' => ['application/vnd.ms-powerpoint', 'application/vnd.ms-office']],
        'pptx' => ['max_kb' => 10240, 'mimes' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation']],
        'txt' => ['max_kb' => 10240, 'mimes' => ['text/plain']],
        'csv' => ['max_kb' => 10240, 'mimes' => ['text/plain', 'text/csv', 'application/csv']],
    ],
];
