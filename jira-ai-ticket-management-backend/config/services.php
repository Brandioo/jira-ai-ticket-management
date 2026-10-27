<?php

return [
    'ollama' => [
        'enabled' => env('OLLAMA_ENABLED', false),
        'url' => env('OLLAMA_URL', 'http://host.docker.internal:11434'),
        'model' => env('OLLAMA_MODEL', 'llama3.2:3b'),
    ],
];
