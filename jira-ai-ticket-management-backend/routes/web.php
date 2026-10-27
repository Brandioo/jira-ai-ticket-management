<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'name' => 'Jira AI Education API',
    'api' => '/api',
]));
