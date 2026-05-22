<?php

return [
    'examples' => [
        'thread_studio' => env('EVO_EXAMPLE_THREAD_STUDIO', true),
        'prd_studio' => env('EVO_EXAMPLE_PRD_STUDIO', true),
        'admin_inbox' => env('EVO_EXAMPLE_ADMIN_INBOX', true),
        'contact_ai' => env('EVO_EXAMPLE_CONTACT_AI', true),
        'voice_input' => env('EVO_EXAMPLE_VOICE_INPUT', true),
        'ai_text_field' => env('EVO_EXAMPLE_AI_TEXT_FIELD', true),
    ],

    'features' => [
        'contact_attachments' => env('EVO_FEATURE_CONTACT_ATTACHMENTS', true),
    ],

    'route' => [
        'middleware' => ['web'],
    ],
];
