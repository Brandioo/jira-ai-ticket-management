<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use App\Services\SprintLifecycleService;
use App\Services\WorklogAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SprintController extends Controller
{
    public function __construct(
        private readonly WorklogAnalyticsService $analytics,
        private readonly SprintLifecycleService $lifecycle,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json(
            Sprint::query()
                ->with('project:id,key,name')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'goal' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        return response()->json(
            $this->lifecycle->create($data),
            201
        );
    }

    public function update(Request $request, Sprint $sprint): JsonResponse
    {
        if ($sprint->status === 'completed') {
            return response()->json([
                'message' => 'Completed sprints cannot be edited.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'goal' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $startsAt = $data['starts_at'] ?? $sprint->starts_at?->toDateString();
        $endsAt = $data['ends_at'] ?? $sprint->ends_at?->toDateString();

        if ($startsAt && $endsAt && $endsAt < $startsAt) {
            return response()->json([
                'message' => 'The sprint end date must be on or after the start date.',
            ], 422);
        }

        $sprint->update($data);

        return response()->json(
            $sprint->fresh()->load('project:id,key,name')
        );
    }

    public function start(Sprint $sprint): JsonResponse
    {
        return response()->json(
            $this->lifecycle->start($sprint)
        );
    }

    public function complete(Request $request, Sprint $sprint): JsonResponse
    {
        $data = $request->validate([
            'next_sprint_id' => ['nullable', 'exists:sprints,id'],
        ]);

        return response()->json(
            $this->lifecycle->complete(
                $sprint,
                $data['next_sprint_id'] ?? null,
                $request->user()
            )
        );
    }

    public function summary(Sprint $sprint): JsonResponse
    {
        return response()->json(
            $this->analytics->sprintSummary($sprint)
        );
    }
}
