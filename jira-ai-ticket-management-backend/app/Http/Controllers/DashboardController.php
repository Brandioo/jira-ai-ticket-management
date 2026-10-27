<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use App\Models\Ticket;
use App\Services\WorklogAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly WorklogAnalyticsService $analytics) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $sprint = Sprint::query()
            ->with('project:id,key,name')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$sprint) {
            return response()->json([
                'active_sprint' => null,
                'ticket_counts' => [],
                'my_open_tickets' => 0,
                'analytics' => null,
            ]);
        }

        $base = Ticket::query()->where('sprint_id', $sprint->id);
        $counts = [
            'total' => (clone $base)->count(),
            'todo' => (clone $base)->where('status', 'todo')->count(),
            'in_progress' => (clone $base)->where('status', 'in_progress')->count(),
            'review' => (clone $base)->where('status', 'review')->count(),
            'done' => (clone $base)->where('status', 'done')->count(),
        ];

        return response()->json([
            'active_sprint' => $sprint,
            'ticket_counts' => $counts,
            'my_open_tickets' => Ticket::query()
                ->where('sprint_id', $sprint->id)
                ->where('assignee_id', $user->id)
                ->where('status', '!=', 'done')
                ->count(),
            'analytics' => $user->isManager()
                ? $this->analytics->sprintSummary($sprint)
                : $this->analytics->employeeSummary($user, $sprint),
        ]);
    }
}
