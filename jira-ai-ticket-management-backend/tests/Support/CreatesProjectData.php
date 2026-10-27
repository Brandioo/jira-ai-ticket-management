<?php

namespace Tests\Support;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Worklog;
use Illuminate\Support\Facades\Hash;

trait CreatesProjectData
{
    protected User $manager;
    protected User $alice;
    protected User $bob;
    protected Project $project;
    protected Sprint $sprint;
    protected Ticket $ticket;

    protected function createProjectData(): void
    {
        $this->manager = User::query()->create([
            'name' => 'Manager User',
            'email' => 'manager@test.dev',
            'password' => Hash::make('password'),
            'role' => 'manager',
        ]);

        $this->alice = User::query()->create([
            'name' => 'Alice Employee',
            'email' => 'alice@test.dev',
            'password' => Hash::make('password'),
            'role' => 'employee',
        ]);

        $this->bob = User::query()->create([
            'name' => 'Bob Employee',
            'email' => 'bob@test.dev',
            'password' => Hash::make('password'),
            'role' => 'employee',
        ]);

        $this->project = Project::query()->create([
            'key' => 'TST',
            'name' => 'Test Project',
        ]);

        $this->sprint = Sprint::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Test Sprint',
            'status' => 'active',
        ]);

        $this->ticket = Ticket::query()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $this->sprint->id,
            'reporter_id' => $this->manager->id,
            'assignee_id' => $this->alice->id,
            'ticket_number' => 'TST-1',
            'title' => 'Authentication dashboard issue',
            'description' => 'Searchable description with overtime analytics.',
            'type' => 'bug',
            'status' => 'in_progress',
            'priority' => 'high',
            'story_points' => 5,
        ]);
    }

    protected function createWorklogs(): void
    {
        Worklog::query()->create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->alice->id,
            'work_date' => '2026-10-05',
            'hours' => 8,
            'overtime_hours' => 0,
        ]);

        Worklog::query()->create([
            'ticket_id' => $this->ticket->id,
            'user_id' => $this->alice->id,
            'work_date' => '2026-10-06',
            'hours' => 10,
            'overtime_hours' => 2,
        ]);
    }
}
