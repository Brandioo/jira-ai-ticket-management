<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Ticket::query()->with([
            'project:id,key,name',
            'sprint:id,project_id,name,status',
            'reporter:id,name,email,role',
            'assignee:id,name,email,role',
        ]);

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        foreach (['status', 'priority', 'type'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, $value);
            }
        }

        foreach (['sprint_id', 'project_id', 'assignee_id'] as $field) {
            if ($value = $request->query($field)) {
                $query->where($field, (int) $value);
            }
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->normalizeEstimate($this->validated($request));
        $this->assertSprintMatchesProject($data, (int) $data['project_id']);
        $project = Project::query()->findOrFail($data['project_id']);

        $next = Ticket::query()
            ->where('project_id', $project->id)
            ->pluck('ticket_number')
            ->map(function (string $number) {
                if (preg_match('/-(\\d+)$/', $number, $matches)) {
                    return (int) $matches[1];
                }

                return 0;
            })
            ->max() + 1;

        $ticket = Ticket::query()->create([
            ...$data,
            'reporter_id' => $request->user()->id,
            'ticket_number' => $project->key.'-'.$next,
        ]);

        return response()->json($this->load($ticket), 201);
    }

    public function show(Ticket $ticket): JsonResponse
    {
        return response()->json($this->load($ticket));
    }

    public function update(Request $request, Ticket $ticket): JsonResponse
    {
        $user = $request->user();

        if (!$user->isManager() && !in_array($user->id, [$ticket->reporter_id, $ticket->assignee_id], true)) {
            return response()->json(['message' => 'You cannot update this ticket.'], 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'sprint_id' => ['sometimes', 'nullable', 'exists:sprints,id'],
            'assignee_id' => ['sometimes', 'nullable', 'exists:users,id'],
            'type' => ['sometimes', Rule::in(['task', 'bug', 'story', 'epic'])],
            'status' => ['sometimes', Rule::in(['todo', 'in_progress', 'review', 'done'])],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'critical'])],
            'estimation_type' => ['sometimes', Rule::in(['story_points', 'hours'])],
            'story_points' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'estimated_hours' => ['sometimes', 'nullable', 'numeric', 'min:0.25', 'max:999'],
        ]);

        $data = $this->normalizeEstimate($data, $ticket);
        $this->assertSprintMatchesProject($data, (int) $ticket->project_id);

        foreach ($data as $field => $value) {
            $oldValue = $ticket->getAttribute($field);

            if ((string) $oldValue !== (string) $value) {
                TicketHistory::query()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'field' => $field,
                    'old_value' => $oldValue === null ? null : (string) $oldValue,
                    'new_value' => $value === null ? null : (string) $value,
                ]);
            }
        }

        $ticket->update($data);

        return response()->json($this->load($ticket));
    }

    public function destroy(Request $request, Ticket $ticket): JsonResponse
    {
        if (!$request->user()->isManager()) {
            return response()->json(['message' => 'Only managers or admins may delete tickets.'], 403);
        }

        $ticket->delete();

        return response()->json(['message' => 'Ticket deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'sprint_id' => ['nullable', 'exists:sprints,id'],
            'assignee_id' => ['nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', Rule::in(['task', 'bug', 'story', 'epic'])],
            'status' => ['required', Rule::in(['todo', 'in_progress', 'review', 'done'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'estimation_type' => ['sometimes', Rule::in(['story_points', 'hours'])],
            'story_points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0.25', 'max:999'],
        ]);
    }

    private function assertSprintMatchesProject(array $data, int $projectId): void
    {
        if (!array_key_exists('sprint_id', $data) || $data['sprint_id'] === null) {
            return;
        }

        $matches = Sprint::query()
            ->whereKey((int) $data['sprint_id'])
            ->where('project_id', $projectId)
            ->exists();

        if (!$matches) {
            throw ValidationException::withMessages([
                'sprint_id' => ['The selected sprint does not belong to the selected project.'],
            ]);
        }
    }

    private function normalizeEstimate(array $data, ?Ticket $ticket = null): array
    {
        $type = $data['estimation_type'] ?? $ticket?->estimation_type ?? 'story_points';
        $data['estimation_type'] = $type;

        $estimationChanged = array_key_exists('estimation_type', $data)
            || array_key_exists('story_points', $data)
            || array_key_exists('estimated_hours', $data);

        if ($estimationChanged) {
            if ($type === 'hours') {
                $data['story_points'] = null;
            } else {
                $data['estimated_hours'] = null;
            }
        }

        return $data;
    }

    private function load(Ticket $ticket): Ticket
    {
        return $ticket->load([
            'project:id,key,name',
            'sprint:id,project_id,name,status',
            'reporter:id,name,email,role',
            'assignee:id,name,email,role',
            'worklogs.user:id,name,email,role',
            'comments.user:id,name,email,role',
            'histories.user:id,name,email,role',
        ]);
    }
}
