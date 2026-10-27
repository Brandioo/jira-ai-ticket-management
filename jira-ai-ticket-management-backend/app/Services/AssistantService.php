<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssistantService
{
    public function __construct(
        private readonly WorklogAnalyticsService $analytics,
        private readonly OllamaService $ollama,
        private readonly SprintLifecycleService $sprintLifecycle,
    ) {}

    public function answer(string $question, ?Sprint $sprint, User $requester): array
    {
        $normalized = Str::lower(trim($question));

        if ($this->isHowToQuestion($normalized)) {
            if (Str::contains($normalized, ['create sprint', 'create a sprint', 'create new sprint', 'create a new sprint'])) {
                return $this->result(
                    'help',
                    'Managers can create a sprint with a name, goal, start date and end date. A normal sprint is 14 days. You can also ask me directly, for example: "Create Sprint 26 from 2026-11-02 to 2026-11-15". If dates are omitted, I create a 14-day planned sprint automatically.',
                    []
                );
            }

            if (Str::contains($normalized, ['create issue', 'create ticket', 'create epic'])) {
                return $this->result(
                    'help',
                    'You can ask me to create work directly. Examples: "Create issue Fix login validation", "Create bug called Broken login assigned to Alice", or "Create epic Customer onboarding". You can then edit the issue by ticket number, for example: "Change AIP-108 status to done" or "Change AIP-108 type to epic".',
                    []
                );
            }
        }

        $questionSprint = $this->resolveSprintFromQuestion($question);

        if ($questionSprint) {
            $sprint = $questionSprint;
        }

        $actionResult = $this->handleAction(
            $question,
            $normalized,
            $sprint,
            $requester
        );

        if ($actionResult !== null) {
            return $actionResult;
        }

        if (!$sprint) {
            $sprint = Sprint::query()
                ->where('status', 'active')
                ->latest('id')
                ->first();
        }

        if (!$sprint) {
            return $this->result(
                'general',
                'There is no active sprint available yet. A manager can create one from the Dashboard or ask me: "Create Sprint 1 from 2026-11-02 to 2026-11-15".',
                []
            );
        }

        $ticketNumber = $this->extractTicketNumber($question);

        if ($ticketNumber) {
            $ticket = Ticket::query()
                ->with([
                    'project:id,key,name',
                    'sprint:id,project_id,name,status,starts_at,ends_at',
                    'assignee:id,name,email,role',
                    'reporter:id,name,email,role',
                ])
                ->where('ticket_number', $ticketNumber)
                ->first();

            if (!$ticket) {
                return $this->result(
                    'ticket_detail',
                    "I couldn't find issue {$ticketNumber}.",
                    ['ticket_number' => $ticketNumber]
                );
            }

            $assignee = $ticket->assignee?->name ?? 'Unassigned';
            $sprintName = $ticket->sprint?->name ?? 'Backlog';

            $answer = "{$ticket->ticket_number} — {$ticket->title}. ".
                "Type: {$this->humanize($ticket->type)}, ".
                "status: {$this->humanize($ticket->status)}, ".
                "priority: {$this->humanize($ticket->priority)}, ".
                "assignee: {$assignee}, sprint: {$sprintName}.";

            return $this->withOptionalLlm(
                $question,
                'ticket_detail',
                $answer,
                ['ticket' => $ticket->toArray()]
            );
        }

        $explicitTicketSearch = $this->isExplicitTicketSearchQuestion($normalized);

        if (!$explicitTicketSearch && $this->isEmployeeAnalyticsQuestion($normalized)) {
            $target = $this->resolveUserFromQuestion($question) ?? $requester;

            if (!$requester->isManager() && $target->id !== $requester->id) {
                throw new HttpException(
                    403,
                    'Employees may only query their own work statistics.'
                );
            }

            $summary = $this->analytics->employeeSummary($target, $sprint);

            $answer = "{$target->name} averages {$summary['average_daily_hours']} hours per logged day in {$sprint->name}. ".
                "Total: {$summary['hours']}h, overtime: {$summary['overtime_hours']}h.";

            return $this->withOptionalLlm(
                $question,
                'employee_analytics',
                $answer,
                $summary
            );
        }

        if (!$explicitTicketSearch && $this->isSprintAnalyticsQuestion($normalized)) {
            if (!$requester->isManager()) {
                $own = $this->analytics->employeeSummary($requester, $sprint);

                return $this->result(
                    'analytics',
                    "You logged {$own['hours']} hours in {$sprint->name}, including {$own['overtime_hours']} overtime hours.",
                    $own
                );
            }

            $summary = $this->analytics->sprintSummary($sprint);

            $answer = "{$sprint->name} has {$summary['total_hours']} total logged hours and ".
                "{$summary['overtime_hours']} overtime hours. ".
                "Average daily logged hours: {$summary['average_daily_hours']}.";

            return $this->withOptionalLlm(
                $question,
                'analytics',
                $answer,
                $summary
            );
        }

        if ($this->isTicketQuestion($normalized)) {
            $query = Ticket::query()
                ->with([
                    'project:id,key,name',
                    'assignee:id,name,email,role',
                    'reporter:id,name,email,role',
                    'sprint:id,project_id,name,status,starts_at,ends_at',
                ])
                ->where('sprint_id', $sprint->id);

            $filters = $this->applyTicketFilters(
                $query,
                $question,
                $normalized,
                $requester
            );

            $term = $this->extractSearchTerm($question);

            if ($term !== '') {
                $query->where(function (Builder $builder) use ($term) {
                    $builder
                        ->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhere('ticket_number', 'like', "%{$term}%");
                });
            }

            $allTickets = $query
                ->orderByDesc('id')
                ->get();

            $count = $allTickets->count();
            $statusCounts = $allTickets
                ->groupBy('status')
                ->map(fn ($items) => $items->count())
                ->toArray();

            $visibleTickets = $allTickets
                ->take(20)
                ->values();

            $data = [
                'sprint' => [
                    'id' => $sprint->id,
                    'name' => $sprint->name,
                    'status' => $sprint->status,
                ],
                'search' => $term,
                'filters' => $filters,
                'count' => $count,
                'status_counts' => $statusCounts,
                'tickets' => $visibleTickets->toArray(),
            ];

            if ($count === 0) {
                $description = $this->describeTicketRequest(
                    $term,
                    $filters,
                    $sprint
                );

                return $this->withOptionalLlm(
                    $question,
                    'ticket_search',
                    "I couldn't find any {$description}.",
                    $data
                );
            }

            if ($this->isCountQuestion($normalized)) {
                $answer = "{$sprint->name} has {$count} matching ticket(s).";

                if ($statusCounts !== []) {
                    $parts = collect($statusCounts)
                        ->map(
                            fn ($value, $status) =>
                                "{$this->humanize((string) $status)}: {$value}"
                        )
                        ->values()
                        ->join(', ');

                    $answer .= " Status breakdown: {$parts}.";
                }

                return $this->withOptionalLlm(
                    $question,
                    'ticket_count',
                    $answer,
                    $data
                );
            }

            $list = $visibleTickets
                ->map(function (Ticket $ticket) {
                    $assignee = $ticket->assignee?->name ?? 'Unassigned';

                    return "{$ticket->ticket_number} — {$ticket->title} ".
                        "[{$this->humanize($ticket->type)}, ".
                        "{$this->humanize($ticket->status)}, ".
                        "{$this->humanize($ticket->priority)}, ".
                        "{$assignee}]";
                })
                ->join('; ');

            $answer = "{$sprint->name} has {$count} matching ticket(s): {$list}.";

            return $this->withOptionalLlm(
                $question,
                'ticket_search',
                $answer,
                $data
            );
        }

        if ($this->isSprintOverviewQuestion($normalized)) {
            return $this->sprintOverview($question, $sprint, $requester);
        }

        $context = $this->generalContext($sprint, $requester);

        $fallback =
            "I can answer questions and perform project actions. I can list/count tickets, inspect status, priority, type, assignee and sprint data, analyze hours/overtime, create sprints, start or complete sprints, create issues or epics, and edit existing issues by ticket number. Examples: ".
            "\"tickets in {$sprint->name}\", \"create epic Customer onboarding\", ".
            "\"change AIP-103 status to done\", \"change AIP-103 type to epic\", ".
            "\"create Sprint 26 from 2026-11-02 to 2026-11-15\", or \"complete Sprint 24\".";

        return $this->withOptionalLlm(
            $question,
            'general',
            $fallback,
            $context
        );
    }

    private function handleAction(
        string $question,
        string $normalized,
        ?Sprint $contextSprint,
        User $requester
    ): ?array {
        if ($this->isCreateSprintCommand($normalized)) {
            $this->requireManager($requester);

            $project = $this->resolveProjectFromQuestion($question)
                ?? $contextSprint?->project
                ?? Project::query()->orderBy('id')->first();

            if (!$project) {
                return $this->result(
                    'sprint_create_error',
                    'Create a project first, then create the sprint.',
                    []
                );
            }

            $dates = $this->extractDates($question);
            $name = $this->extractSprintNameForCreate($question);
            $goal = $this->extractNamedField($question, 'goal');

            $created = $this->sprintLifecycle->create([
                'project_id' => $project->id,
                'name' => $name,
                'goal' => $goal,
                'starts_at' => $dates[0] ?? null,
                'ends_at' => $dates[1] ?? null,
            ]);

            return $this->result(
                'sprint_created',
                "Created {$created->name} as a planned sprint from {$created->starts_at?->format('Y-m-d')} to {$created->ends_at?->format('Y-m-d')}.",
                ['sprint' => $created->toArray()]
            );
        }

        if ($this->isStartSprintCommand($normalized)) {
            $this->requireManager($requester);

            $target = $this->resolveSprintFromQuestion($question)
                ?? $contextSprint;

            if (!$target) {
                return $this->result(
                    'sprint_start_error',
                    'Tell me which sprint to start, for example: "Start Sprint 25".',
                    []
                );
            }

            $started = $this->sprintLifecycle->start($target);

            return $this->result(
                'sprint_started',
                "Started {$started->name}. It runs from {$started->starts_at?->format('Y-m-d')} to {$started->ends_at?->format('Y-m-d')}.",
                ['sprint' => $started->toArray()]
            );
        }

        if ($this->isCompleteSprintCommand($normalized)) {
            $this->requireManager($requester);

            $target = $this->resolveSprintFromQuestion($question)
                ?? $contextSprint;

            if (!$target) {
                return $this->result(
                    'sprint_complete_error',
                    'Tell me which sprint to complete, for example: "Complete Sprint 24".',
                    []
                );
            }

            $result = $this->sprintLifecycle->complete(
                $target,
                null,
                $requester
            );

            $next = $result['next_sprint'];
            $moved = $result['moved_tickets'];

            return $this->result(
                'sprint_completed',
                "Completed {$target->name}. {$moved} unfinished ticket(s) were moved to {$next->name}. Done tickets remain in the completed sprint. {$next->name} is planned from {$next->starts_at?->format('Y-m-d')} to {$next->ends_at?->format('Y-m-d')}.",
                $result
            );
        }

        if ($this->isCreateIssueCommand($normalized)) {
            return $this->createIssueFromQuestion(
                $question,
                $contextSprint,
                $requester
            );
        }

        if ($this->isUpdateIssueCommand($normalized, $question)) {
            return $this->updateIssueFromQuestion(
                $question,
                $requester
            );
        }

        return null;
    }

    private function createIssueFromQuestion(
        string $question,
        ?Sprint $contextSprint,
        User $requester
    ): array {
        $explicitSprint = $this->resolveSprintFromQuestion($question);
        $sprint = $explicitSprint
            ?? $contextSprint
            ?? Sprint::query()->where('status', 'active')->latest('id')->first();

        $project = $this->resolveProjectFromQuestion($question)
            ?? $sprint?->project
            ?? Project::query()->orderBy('id')->first();

        if (!$project) {
            return $this->result(
                'issue_create_error',
                'Create a project first, then create the issue.',
                []
            );
        }

        if ($sprint && (int) $sprint->project_id !== (int) $project->id) {
            $sprint = null;
        }

        $type = $this->resolveTicketTypeFromCreateCommand($question);
        $title = $this->extractIssueTitleForCreate($question);

        if ($title === '') {
            return $this->result(
                'issue_create_error',
                'Tell me the issue summary. Example: "Create bug called Fix login redirect".',
                []
            );
        }

        $assignee = Str::contains(Str::lower($question), ['assign', 'assigned to', 'assignee'])
            ? $this->resolveUserFromQuestion($question)
            : null;

        $priority = $this->resolvePriority(Str::lower($question)) ?? 'medium';
        $description = $this->extractNamedField($question, 'description');

        $next = Ticket::query()
            ->where('project_id', $project->id)
            ->pluck('ticket_number')
            ->map(function (string $number) {
                return preg_match('/-(\d+)$/', $number, $matches)
                    ? (int) $matches[1]
                    : 0;
            })
            ->max() + 1;

        $ticket = Ticket::query()->create([
            'project_id' => $project->id,
            'sprint_id' => $sprint?->id,
            'reporter_id' => $requester->id,
            'assignee_id' => $assignee?->id,
            'ticket_number' => $project->key.'-'.$next,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'status' => 'todo',
            'priority' => $priority,
            'estimation_type' => 'story_points',
            'story_points' => null,
            'estimated_hours' => null,
        ]);

        $ticket->load([
            'project:id,key,name',
            'sprint:id,project_id,name,status',
            'reporter:id,name,email,role',
            'assignee:id,name,email,role',
        ]);

        return $this->result(
            'issue_created',
            "Created {$ticket->ticket_number} — {$ticket->title} as {$this->humanize($ticket->type)}".
            ($ticket->sprint ? " in {$ticket->sprint->name}" : ' in the backlog').
            '.',
            ['ticket' => $ticket->toArray()]
        );
    }

    private function updateIssueFromQuestion(
        string $question,
        User $requester
    ): array {
        $ticketNumber = $this->extractTicketNumber($question);

        if (!$ticketNumber) {
            return $this->result(
                'issue_update_error',
                'Include the ticket number, for example: "Change AIP-103 status to done".',
                []
            );
        }

        $ticket = Ticket::query()
            ->with(['project', 'sprint', 'assignee'])
            ->where('ticket_number', $ticketNumber)
            ->first();

        if (!$ticket) {
            return $this->result(
                'issue_update_error',
                "I couldn't find issue {$ticketNumber}.",
                ['ticket_number' => $ticketNumber]
            );
        }

        if (
            !$requester->isManager() &&
            !in_array(
                $requester->id,
                [$ticket->reporter_id, $ticket->assignee_id],
                true
            )
        ) {
            throw new HttpException(
                403,
                'You cannot update this ticket.'
            );
        }

        $normalized = Str::lower($question);
        $changes = [];

        if (preg_match('/\b(?:status)\s*(?:to|is|=|:)?\s*(todo|to do|in progress|review|in review|done)\b/i', $question, $matches)) {
            $statusText = Str::lower($matches[1]);
            $changes['status'] = match ($statusText) {
                'todo', 'to do' => 'todo',
                'in progress' => 'in_progress',
                'review', 'in review' => 'review',
                'done' => 'done',
                default => $ticket->status,
            };
        }

        if (preg_match('/\bpriority\s*(?:to|is|=|:)?\s*(low|medium|high|critical)\b/i', $question, $matches)) {
            $changes['priority'] = Str::lower($matches[1]);
        }

        if (preg_match('/\b(?:issue\s+type|type)\s*(?:to|is|=|:)?\s*(task|bug|story|epic)\b/i', $question, $matches)) {
            $changes['type'] = Str::lower($matches[1]);
        }

        if (
            Str::contains($normalized, ['assign', 'assignee']) &&
            Str::contains($normalized, ['unassigned', 'no assignee'])
        ) {
            $changes['assignee_id'] = null;
        } elseif (Str::contains($normalized, ['assign', 'assignee'])) {
            $assignee = $this->resolveUserFromQuestion($question);

            if ($assignee) {
                $changes['assignee_id'] = $assignee->id;
            }
        }

        if (
            Str::contains($normalized, ['sprint', 'move']) &&
            $newSprint = $this->resolveSprintFromQuestion($question)
        ) {
            if ((int) $newSprint->project_id !== (int) $ticket->project_id) {
                throw ValidationException::withMessages([
                    'sprint_id' => ['The selected sprint does not belong to the ticket project.'],
                ]);
            }

            $changes['sprint_id'] = $newSprint->id;
        }

        $title = $this->extractUpdateFieldValue($question, 'title');

        if ($title !== null) {
            $changes['title'] = $title;
        }

        $description = $this->extractUpdateFieldValue(
            $question,
            'description'
        );

        if ($description !== null) {
            $changes['description'] = $description;
        }

        if ($changes === []) {
            return $this->result(
                'issue_update_error',
                "I found {$ticketNumber}, but I couldn't determine what to change. Try: \"Change {$ticketNumber} status to done\", \"Change {$ticketNumber} type to epic\", or \"Change {$ticketNumber} description to ...\".",
                ['ticket' => $ticket->toArray()]
            );
        }

        foreach ($changes as $field => $value) {
            $oldValue = $ticket->getAttribute($field);

            if ((string) $oldValue === (string) $value) {
                continue;
            }

            TicketHistory::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $requester->id,
                'field' => $field,
                'old_value' => $oldValue === null ? null : (string) $oldValue,
                'new_value' => $value === null ? null : (string) $value,
            ]);
        }

        $ticket->update($changes);
        $ticket->load([
            'project:id,key,name',
            'sprint:id,project_id,name,status',
            'reporter:id,name,email,role',
            'assignee:id,name,email,role',
        ]);

        $changedFields = collect(array_keys($changes))
            ->map(fn (string $field) => str_replace('_', ' ', $field))
            ->join(', ');

        return $this->result(
            'issue_updated',
            "Updated {$ticket->ticket_number}. Changed: {$changedFields}.",
            [
                'changes' => $changes,
                'ticket' => $ticket->toArray(),
            ]
        );
    }

    private function sprintOverview(
        string $question,
        Sprint $sprint,
        User $requester
    ): array {
        $tickets = Ticket::query()
            ->with([
                'assignee:id,name,email,role',
                'reporter:id,name,email,role',
            ])
            ->where('sprint_id', $sprint->id)
            ->orderByDesc('id')
            ->get();

        $statusCounts = $tickets
            ->groupBy('status')
            ->map(fn ($items) => $items->count())
            ->toArray();

        $analytics = $requester->isManager()
            ? $this->analytics->sprintSummary($sprint)
            : $this->analytics->employeeSummary($requester, $sprint);

        $answer = "{$sprint->name} is {$this->humanize($sprint->status)} and contains ".
            $tickets->count()." ticket(s).";

        if ($statusCounts !== []) {
            $breakdown = collect($statusCounts)
                ->map(
                    fn ($value, $status) =>
                        "{$this->humanize((string) $status)}: {$value}"
                )
                ->values()
                ->join(', ');

            $answer .= " Status breakdown: {$breakdown}.";
        }

        return $this->withOptionalLlm(
            $question,
            'sprint_overview',
            $answer,
            [
                'sprint' => $sprint->toArray(),
                'ticket_count' => $tickets->count(),
                'status_counts' => $statusCounts,
                'tickets' => $tickets->take(20)->values()->toArray(),
                'analytics' => $analytics,
            ]
        );
    }

    private function generalContext(
        Sprint $sprint,
        User $requester
    ): array {
        $tickets = Ticket::query()
            ->with([
                'project:id,key,name',
                'sprint:id,project_id,name,status',
                'assignee:id,name,email,role',
                'reporter:id,name,email,role',
            ])
            ->where('sprint_id', $sprint->id)
            ->latest('id')
            ->limit(30)
            ->get();

        $analytics = $requester->isManager()
            ? $this->analytics->sprintSummary($sprint)
            : $this->analytics->employeeSummary($requester, $sprint);

        return [
            'sprint' => [
                'id' => $sprint->id,
                'name' => $sprint->name,
                'goal' => $sprint->goal,
                'status' => $sprint->status,
                'starts_at' => $sprint->starts_at?->format('Y-m-d'),
                'ends_at' => $sprint->ends_at?->format('Y-m-d'),
            ],
            'ticket_count' => $tickets->count(),
            'tickets' => $tickets->toArray(),
            'analytics' => $analytics,
        ];
    }

    private function applyTicketFilters(
        Builder $query,
        string $question,
        string $normalized,
        User $requester
    ): array {
        $filters = [];

        $status = $this->resolveStatus($normalized);

        if ($status) {
            $query->where('status', $status);
            $filters['status'] = $status;
        }

        $priority = $this->resolvePriority($normalized);

        if ($priority) {
            $query->where('priority', $priority);
            $filters['priority'] = $priority;
        }

        if (preg_match('/\b(epic|bug|story|task)s?\b/i', $question, $matches)) {
            $type = Str::lower($matches[1]);
            $query->where('type', $type);
            $filters['type'] = $type;
        }

        if (
            Str::contains(
                $normalized,
                ['unassigned', 'without assignee', 'no assignee']
            )
        ) {
            $query->whereNull('assignee_id');
            $filters['assignee'] = 'unassigned';

            return $filters;
        }

        if (
            Str::contains(
                $normalized,
                ['my ticket', 'my tickets', 'assigned to me', 'for me']
            )
        ) {
            $query->where('assignee_id', $requester->id);
            $filters['assignee_id'] = $requester->id;
            $filters['assignee'] = $requester->name;

            return $filters;
        }

        if (
            Str::contains(
                $normalized,
                ['assigned to', 'assignee', 'owned by', 'owner']
            )
        ) {
            $target = $this->resolveUserFromQuestion($question);

            if ($target) {
                $query->where('assignee_id', $target->id);
                $filters['assignee_id'] = $target->id;
                $filters['assignee'] = $target->name;
            }
        }

        return $filters;
    }

    private function resolveSprintFromQuestion(
        string $question
    ): ?Sprint {
        if (
            preg_match(
                '/\bsprint\s*#?\s*(\d+)\b/i',
                $question,
                $matches
            )
        ) {
            $number = (int) $matches[1];

            $byName = Sprint::query()
                ->whereRaw('LOWER(name) = ?', ['sprint '.$number])
                ->latest('id')
                ->first();

            if ($byName) {
                return $byName;
            }

            $byId = Sprint::query()->find($number);

            if ($byId) {
                return $byId;
            }
        }

        $normalized = Str::lower($question);

        return Sprint::query()
            ->with('project')
            ->orderByDesc('id')
            ->get()
            ->first(function (Sprint $candidate) use ($normalized) {
                $name = Str::lower(trim($candidate->name));

                return $name !== '' &&
                    Str::contains($normalized, $name);
            });
    }

    private function resolveProjectFromQuestion(
        string $question
    ): ?Project {
        $normalized = Str::lower($question);

        return Project::query()
            ->orderBy('id')
            ->get()
            ->first(function (Project $project) use ($normalized) {
                return Str::contains(
                    $normalized,
                    Str::lower($project->name)
                ) || preg_match(
                    '/\b'.preg_quote(Str::lower($project->key), '/').'\b/i',
                    $normalized
                );
            });
    }

    private function resolveUserFromQuestion(
        string $question
    ): ?User {
        $normalized = Str::lower($question);

        return User::query()
            ->orderByDesc('id')
            ->get()
            ->first(function (User $user) use ($normalized) {
                return Str::contains(
                    $normalized,
                    Str::lower($user->name)
                ) || Str::contains(
                    $normalized,
                    Str::lower($user->email)
                );
            });
    }

    private function resolveStatus(
        string $normalized
    ): ?string {
        if (
            Str::contains(
                $normalized,
                ['in progress', 'in-progress', 'working on']
            )
        ) {
            return 'in_progress';
        }

        if (
            Str::contains(
                $normalized,
                ['in review', 'reviewing', 'review tickets', 'review issues']
            )
        ) {
            return 'review';
        }

        if (
            Str::contains(
                $normalized,
                ['done', 'completed', 'finished', 'closed']
            )
        ) {
            return 'done';
        }

        if (
            Str::contains(
                $normalized,
                ['to do', 'todo', 'not started']
            )
        ) {
            return 'todo';
        }

        return null;
    }

    private function resolvePriority(
        string $normalized
    ): ?string {
        if (
            Str::contains(
                $normalized,
                ['critical priority', 'priority critical', 'critical ticket', 'critical issue']
            )
        ) {
            return 'critical';
        }

        if (
            Str::contains(
                $normalized,
                ['high priority', 'priority high', 'high-priority']
            )
        ) {
            return 'high';
        }

        if (
            Str::contains(
                $normalized,
                ['medium priority', 'priority medium', 'medium-priority']
            )
        ) {
            return 'medium';
        }

        if (
            Str::contains(
                $normalized,
                ['low priority', 'priority low', 'low-priority']
            )
        ) {
            return 'low';
        }

        return null;
    }

    private function isExplicitTicketSearchQuestion(
        string $normalized
    ): bool {
        if (!$this->isTicketQuestion($normalized)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(find|search|show|list|give|tickets?|issues?|bugs?|tasks?|stories?|epics?)\b.*\b(about|containing|contains|matching|match|title|description|named|called)\b/i',
            $normalized
        );
    }

    private function isTicketQuestion(
        string $normalized
    ): bool {
        return Str::contains(
            $normalized,
            [
                'ticket',
                'tickets',
                'issue',
                'issues',
                'bug',
                'bugs',
                'task',
                'tasks',
                'story',
                'stories',
                'epic',
                'epics',
            ]
        );
    }

    private function isCountQuestion(
        string $normalized
    ): bool {
        return Str::contains(
            $normalized,
            [
                'how many',
                'count',
                'number of',
                'total tickets',
                'total issues',
            ]
        );
    }

    private function isSprintAnalyticsQuestion(
        string $normalized
    ): bool {
        return Str::contains(
            $normalized,
            [
                'overtime',
                'sprint summary',
                'team hours',
                'total hours',
                'logged hours',
                'hours in this sprint',
                'hours in sprint',
            ]
        );
    }

    private function isEmployeeAnalyticsQuestion(
        string $normalized
    ): bool {
        return Str::contains(
            $normalized,
            [
                'average hours',
                'average daily',
                'hours for',
                'workload for',
                'my average',
                'my hours',
                'employee workload',
            ]
        );
    }

    private function isSprintOverviewQuestion(
        string $normalized
    ): bool {
        return Str::contains($normalized, 'sprint') &&
            Str::contains(
                $normalized,
                [
                    'overview',
                    'show',
                    'what is',
                    'what\'s',
                    'what is in',
                    'details',
                    'info',
                    'information',
                ]
            );
    }

    private function isHowToQuestion(string $normalized): bool
    {
        return (bool) preg_match(
            '/^\s*(how can i|how do i|how to|can i|what is the way)/i',
            $normalized
        );
    }

    private function isCreateSprintCommand(string $normalized): bool
    {
        return (bool) preg_match(
            '/^\s*(?:please\s+)?(?:create|add|make)\s+(?:a\s+|new\s+)?sprint\b/i',
            $normalized
        );
    }

    private function isStartSprintCommand(string $normalized): bool
    {
        return (bool) preg_match(
            '/^\s*(?:please\s+)?start\s+(?:the\s+)?sprint\b/i',
            $normalized
        ) || (bool) preg_match(
            '/^\s*(?:please\s+)?start\s+sprint\s+\d+/i',
            $normalized
        );
    }

    private function isCompleteSprintCommand(string $normalized): bool
    {
        return (bool) preg_match(
            '/^\s*(?:please\s+)?complete\s+(?:the\s+)?sprint\b/i',
            $normalized
        ) || (bool) preg_match(
            '/^\s*(?:please\s+)?complete\s+sprint\s+\d+/i',
            $normalized
        );
    }

    private function isCreateIssueCommand(string $normalized): bool
    {
        return (bool) preg_match(
            '/^\s*(?:please\s+)?(?:create|add)\s+(?:me\s+)?(?:a\s+|an\s+)?(?:issue|ticket|task|bug|story|epic)\b/i',
            $normalized
        );
    }

    private function isUpdateIssueCommand(
        string $normalized,
        string $question
    ): bool {
        return $this->extractTicketNumber($question) !== null &&
            (bool) preg_match(
                '/\b(update|edit|change|set|assign|move)\b/i',
                $normalized
            );
    }

    private function extractTicketNumber(
        string $question
    ): ?string {
        if (
            preg_match(
                '/\b([A-Z][A-Z0-9]+-\d+)\b/i',
                $question,
                $matches
            )
        ) {
            return Str::upper($matches[1]);
        }

        return null;
    }

    private function extractSearchTerm(
        string $question
    ): string {
        if (
            preg_match(
                '/["\']([^"\']+)["\']/',
                $question,
                $matches
            )
        ) {
            return trim($matches[1]);
        }

        $hasTextSearchIntent = preg_match(
            '/\b(about|containing|contains|matching|match|titled|title|description|named|called)\b/i',
            $question
        );

        if (!$hasTextSearchIntent) {
            return '';
        }

        $normalized = preg_replace(
            '/\bsprint\s*#?\s*\d+\b/i',
            ' ',
            $question
        );

        $normalized = preg_replace(
            '/\b(find|search|show|list|give|me|tickets?|issues?|bugs?|tasks?|stories?|epics?|about|for|with|title|description|containing|contains|matching|match|titled|named|called|in)\b/i',
            ' ',
            (string) $normalized
        );

        $normalized = preg_replace(
            '/\s+/',
            ' ',
            (string) $normalized
        );

        return trim(
            (string) $normalized,
            " ?.,"
        );
    }

    private function extractDates(string $question): array
    {
        preg_match_all(
            '/\b(\d{4}-\d{2}-\d{2})\b/',
            $question,
            $matches
        );

        return $matches[1] ?? [];
    }

    private function extractSprintNameForCreate(
        string $question
    ): ?string {
        if (
            preg_match(
                '/\b(Sprint\s+\d+)\b/i',
                $question,
                $matches
            )
        ) {
            return Str::title(Str::lower($matches[1]));
        }

        if (
            preg_match(
                '/\b(?:called|named)\s+["\']?([^"\',]+)["\']?/i',
                $question,
                $matches
            )
        ) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractNamedField(
        string $question,
        string $field
    ): ?string {
        $quotedPattern = '/\b'.preg_quote($field, '/').'\s*(?:to|is|=|:)?\s*["\']([^"\']+)["\']/i';

        if (preg_match($quotedPattern, $question, $matches)) {
            return trim($matches[1]);
        }

        $plainPattern = '/\b'.preg_quote($field, '/').'\s*(?:to|is|=|:)\s*(.+)$/i';

        if (preg_match($plainPattern, $question, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B.,");
        }

        return null;
    }

    private function resolveTicketTypeFromCreateCommand(
        string $question
    ): string {
        if (
            preg_match(
                '/\b(epic|bug|story|task)\b/i',
                $question,
                $matches
            )
        ) {
            return Str::lower($matches[1]);
        }

        return 'task';
    }

    private function extractIssueTitleForCreate(
        string $question
    ): string {
        if (
            preg_match(
                '/\b(?:called|named|titled)\s+["\']([^"\']+)["\']/i',
                $question,
                $matches
            )
        ) {
            return trim($matches[1]);
        }

        if (
            preg_match(
                '/\b(?:called|named|titled)\s+(.+?)(?=\s+(?:in\s+sprint|assigned\s+to|priority|description)\b|$)/i',
                $question,
                $matches
            )
        ) {
            return trim($matches[1], " \t\n\r\0\x0B.,");
        }

        $title = preg_replace(
            '/^\s*(?:please\s+)?(?:create|add)\s+(?:me\s+)?(?:a\s+|an\s+)?(?:issue|ticket|task|bug|story|epic)\s*/i',
            '',
            $question
        );

        $title = preg_replace(
            '/\s+(?:in\s+sprint\s*#?\s*\d+|assigned\s+to\s+.+|priority\s+(?:low|medium|high|critical)|description\s*(?:to|is|=|:).+)$/i',
            '',
            (string) $title
        );

        return trim(
            (string) $title,
            " \t\n\r\0\x0B.,\"'"
        );
    }

    private function extractUpdateFieldValue(
        string $question,
        string $field
    ): ?string {
        $ticketPattern = '[A-Z][A-Z0-9]+-\d+';

        $quotedPatterns = [
            '/\b'.preg_quote($field, '/').'\s*(?:of\s+'.$ticketPattern.'\s+)?(?:to|as|=|:)\s*["\']([^"\']+)["\']/i',
            '/\b'.preg_quote($field, '/').'\s*["\']([^"\']+)["\']/i',
        ];

        foreach ($quotedPatterns as $pattern) {
            if (preg_match($pattern, $question, $matches)) {
                return trim($matches[1]);
            }
        }

        $plainPattern =
            '/\b'.preg_quote($field, '/').'\s*(?:of\s+'.$ticketPattern.'\s+)?(?:to|as|=|:)\s*(.+)$/i';

        if (preg_match($plainPattern, $question, $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B.,");
        }

        return null;
    }

    private function describeTicketRequest(
        string $term,
        array $filters,
        Sprint $sprint
    ): string {
        $parts = [];

        if (isset($filters['status'])) {
            $parts[] = $this->humanize(
                (string) $filters['status']
            );
        }

        if (isset($filters['priority'])) {
            $parts[] = $this->humanize(
                (string) $filters['priority']
            ).' priority';
        }

        if (isset($filters['type'])) {
            $parts[] = $this->humanize(
                (string) $filters['type']
            );
        }

        if (isset($filters['assignee'])) {
            $parts[] = 'assigned to '.
                $filters['assignee'];
        }

        $parts[] = 'tickets';

        if ($term !== '') {
            $parts[] = "matching \"{$term}\"";
        }

        $parts[] = "in {$sprint->name}";

        return implode(' ', $parts);
    }

    private function humanize(
        string $value
    ): string {
        return Str::of($value)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function requireManager(User $requester): void
    {
        if (!$requester->isManager()) {
            throw new HttpException(
                403,
                'Only managers or admins can manage sprints.'
            );
        }
    }

    private function withOptionalLlm(
        string $question,
        string $mode,
        string $fallback,
        array $context
    ): array {
        $llmAnswer = $this->ollama->answer(
            $question,
            $context
        );

        return $this->result(
            $mode,
            $llmAnswer ?: $fallback,
            $context,
            $llmAnswer ? 'ollama' : 'local'
        );
    }

    private function result(
        string $mode,
        string $answer,
        array $data,
        string $engine = 'local'
    ): array {
        return [
            'mode' => $mode,
            'engine' => $engine,
            'answer' => $answer,
            'data' => $data,
        ];
    }
}
