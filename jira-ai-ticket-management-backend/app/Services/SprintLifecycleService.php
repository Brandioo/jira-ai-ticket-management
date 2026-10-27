<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SprintLifecycleService
{
    public function create(array $data): Sprint
    {
        $start = isset($data['starts_at']) && $data['starts_at']
            ? Carbon::parse($data['starts_at'])->startOfDay()
            : $this->defaultStartDate((int) $data['project_id']);

        $end = isset($data['ends_at']) && $data['ends_at']
            ? Carbon::parse($data['ends_at'])->startOfDay()
            : $start->copy()->addDays(13);

        if ($end->lt($start)) {
            throw ValidationException::withMessages([
                'ends_at' => ['The sprint end date must be on or after the start date.'],
            ]);
        }

        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            $name = $this->nextSprintName((int) $data['project_id']);
        }

        return Sprint::query()->create([
            'project_id' => (int) $data['project_id'],
            'name' => $name,
            'goal' => $data['goal'] ?? null,
            'status' => 'planned',
            'starts_at' => $start->toDateString(),
            'ends_at' => $end->toDateString(),
        ])->load('project:id,key,name');
    }

    public function start(Sprint $sprint): Sprint
    {
        if ($sprint->status === 'completed') {
            throw ValidationException::withMessages([
                'sprint' => ['A completed sprint cannot be started again.'],
            ]);
        }

        $otherActiveExists = Sprint::query()
            ->where('project_id', $sprint->project_id)
            ->where('status', 'active')
            ->whereKeyNot($sprint->id)
            ->exists();

        if ($otherActiveExists) {
            throw ValidationException::withMessages([
                'sprint' => ['Complete the current active sprint before starting another sprint for this project.'],
            ]);
        }

        $startsAt = $sprint->starts_at ?? Carbon::today();
        $endsAt = $sprint->ends_at ?? Carbon::parse($startsAt)->addDays(13);

        $sprint->update([
            'status' => 'active',
            'starts_at' => Carbon::parse($startsAt)->toDateString(),
            'ends_at' => Carbon::parse($endsAt)->toDateString(),
        ]);

        return $sprint->fresh()->load('project:id,key,name');
    }

    public function complete(
        Sprint $sprint,
        ?int $nextSprintId,
        User $actor
    ): array {
        if ($sprint->status !== 'active') {
            throw ValidationException::withMessages([
                'sprint' => ['Only an active sprint can be completed.'],
            ]);
        }

        return DB::transaction(function () use ($sprint, $nextSprintId, $actor) {
            $nextSprint = $this->resolveNextSprint($sprint, $nextSprintId);

            $unfinishedTickets = Ticket::query()
                ->where('sprint_id', $sprint->id)
                ->where('status', '!=', 'done')
                ->get();

            foreach ($unfinishedTickets as $ticket) {
                TicketHistory::query()->create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $actor->id,
                    'field' => 'sprint_id',
                    'old_value' => (string) $sprint->id,
                    'new_value' => (string) $nextSprint->id,
                ]);

                $ticket->update([
                    'sprint_id' => $nextSprint->id,
                ]);
            }

            $sprint->update([
                'status' => 'completed',
                'ends_at' => $sprint->ends_at ?? Carbon::today()->toDateString(),
            ]);

            return [
                'completed_sprint' => $sprint->fresh()->load('project:id,key,name'),
                'next_sprint' => $nextSprint->fresh()->load('project:id,key,name'),
                'moved_tickets' => $unfinishedTickets->count(),
            ];
        });
    }

    private function resolveNextSprint(
        Sprint $sprint,
        ?int $nextSprintId
    ): Sprint {
        if ($nextSprintId) {
            $next = Sprint::query()->findOrFail($nextSprintId);

            if (
                (int) $next->project_id !== (int) $sprint->project_id ||
                $next->status === 'completed'
            ) {
                throw ValidationException::withMessages([
                    'next_sprint_id' => ['Choose a planned sprint from the same project.'],
                ]);
            }

            return $next;
        }

        $planned = Sprint::query()
            ->where('project_id', $sprint->project_id)
            ->where('status', 'planned')
            ->whereKeyNot($sprint->id)
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->orderBy('id')
            ->first();

        if ($planned) {
            return $planned;
        }

        $start = $sprint->ends_at
            ? Carbon::parse($sprint->ends_at)->addDay()
            : Carbon::today();

        if ($start->lt(Carbon::today())) {
            $start = Carbon::today();
        }

        return Sprint::query()->create([
            'project_id' => $sprint->project_id,
            'name' => $this->nextSprintName((int) $sprint->project_id),
            'goal' => null,
            'status' => 'planned',
            'starts_at' => $start->toDateString(),
            'ends_at' => $start->copy()->addDays(13)->toDateString(),
        ]);
    }

    private function defaultStartDate(int $projectId): Carbon
    {
        $latestEnd = Sprint::query()
            ->where('project_id', $projectId)
            ->whereNotNull('ends_at')
            ->max('ends_at');

        if (!$latestEnd) {
            return Carbon::today();
        }

        $candidate = Carbon::parse($latestEnd)->addDay();

        return $candidate->lt(Carbon::today())
            ? Carbon::today()
            : $candidate;
    }

    private function nextSprintName(int $projectId): string
    {
        $max = Sprint::query()
            ->where('project_id', $projectId)
            ->pluck('name')
            ->map(function (string $name) {
                return preg_match('/^Sprint\s+(\d+)$/i', trim($name), $matches)
                    ? (int) $matches[1]
                    : 0;
            })
            ->max();

        return 'Sprint '.(($max ?: 0) + 1);
    }
}
