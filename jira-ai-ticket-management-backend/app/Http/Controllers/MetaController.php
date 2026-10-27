<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class MetaController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'projects' => Project::query()->orderBy('name')->get(['id', 'key', 'name']),
            'sprints' => Sprint::query()->with('project:id,key,name')->orderByDesc('id')->get(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email', 'role']),
            'statuses' => ['todo', 'in_progress', 'review', 'done'],
            'priorities' => ['low', 'medium', 'high', 'critical'],
            'types' => ['task', 'bug', 'story', 'epic'],
        ]);
    }
}
