<?php

return [
    'model_path' => 'App\Models',
    'file_table' => 'system_files',
    'model_sync' => [
        'User' => 'RainLab\User\Models\User'
    ],
    'extention' => ['jpg', 'jpeg', 'png', 'gif', 'docx', 'xlsx', 'svg', 'pdf']
];