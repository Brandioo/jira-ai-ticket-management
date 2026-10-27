<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use App\Models\User;
use App\Services\WorklogAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeReportController extends Controller
{
    public function __construct(private readonly WorklogAnalyticsService $analytics) {}

    public function show(Request $request, User $user): JsonResponse
    {
        $requester = $request->user();

        if (!$requester->isManager() && $requester->id !== $user->id) {
            return response()->json(['message' => 'Employees may only view their own statistics.'], 403);
        }

        $request->validate([
            'sprint_id' => ['required', 'exists:sprints,id'],
        ]);

        $sprint = Sprint::query()->findOrFail((int) $request->query('sprint_id'));

        return response()->json($this->analytics->employeeSummary($user, $sprint));
    }
}
