<?php

namespace Database\Seeders;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Worklog;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // User has a "hashed" password cast, so plain values are hashed exactly once.
        $admin = User::query()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $manager = User::query()->create([
            'name' => 'Maria Manager',
            'email' => 'manager@example.com',
            'password' => 'password',
            'role' => 'manager',
        ]);

        $alice = User::query()->create([
            'name' => 'Alice Employee',
            'email' => 'alice@example.com',
            'password' => 'password',
            'role' => 'employee',
        ]);

        $bob = User::query()->create([
            'name' => 'Bob Employee',
            'email' => 'bob@example.com',
            'password' => 'password',
            'role' => 'employee',
        ]);

        $project = Project::query()->create([
            'key' => 'AIP',
            'name' => 'AI Support Platform',
            'description' => 'Jira-style education project for tickets, sprints, worklogs and AI-assisted analytics.',
        ]);

        $previousSprint = Sprint::query()->create([
            'project_id' => $project->id,
            'name' => 'Sprint 23',
            'goal' => 'Prepare authentication and the initial ticket workflow.',
            'status' => 'completed',
            'starts_at' => '2026-09-21',
            'ends_at' => '2026-10-02',
        ]);

        $sprint = Sprint::query()->create([
            'project_id' => $project->id,
            'name' => 'Sprint 24',
            'goal' => 'Finish authentication, workload analytics and AI ticket search.',
            'status' => 'active',
            'starts_at' => '2026-10-05',
            'ends_at' => '2026-10-16',
        ]);

        Sprint::query()->create([
            'project_id' => $project->id,
            'name' => 'Sprint 25',
            'goal' => 'Improve reporting, automation and project intelligence.',
            'status' => 'planned',
            'starts_at' => '2026-10-19',
            'ends_at' => '2026-10-30',
        ]);

        Ticket::query()->create([
            'project_id' => $project->id,
            'sprint_id' => $previousSprint->id,
            'reporter_id' => $manager->id,
            'assignee_id' => $alice->id,
            'ticket_number' => 'AIP-100',
            'title' => 'Create initial project structure',
            'description' => 'Set up the Laravel API, React frontend and initial project entities.',
            'type' => 'task',
            'status' => 'done',
            'priority' => 'medium',
            'estimation_type' => 'hours',
            'estimated_hours' => 6,
        ]);

        $tickets = [
            Ticket::query()->create([
                'project_id' => $project->id,
                'sprint_id' => $sprint->id,
                'reporter_id' => $manager->id,
                'assignee_id' => $alice->id,
                'ticket_number' => 'AIP-101',
                'title' => 'Implement login and role permissions',
                'description' => "Create login, registration and access rules for admin, manager and employee users.\n\nAcceptance criteria:\n- Users can sign in and sign up.\n- New users receive the Employee role.\n- Protected API endpoints require a valid token.",
                'type' => 'story',
                'status' => 'done',
                'priority' => 'critical',
                'estimation_type' => 'story_points',
                'story_points' => 5,
            ]),
            Ticket::query()->create([
                'project_id' => $project->id,
                'sprint_id' => $sprint->id,
                'reporter_id' => $manager->id,
                'assignee_id' => $bob->id,
                'ticket_number' => 'AIP-102',
                'title' => 'Build sprint analytics dashboard',
                'description' => 'Display total hours, overtime, average employee hours and ticket status metrics.',
                'type' => 'story',
                'status' => 'in_progress',
                'priority' => 'high',
                'estimation_type' => 'hours',
                'estimated_hours' => 16,
            ]),
            Ticket::query()->create([
                'project_id' => $project->id,
                'sprint_id' => $sprint->id,
                'reporter_id' => $admin->id,
                'assignee_id' => $alice->id,
                'ticket_number' => 'AIP-103',
                'title' => 'AI search for ticket titles and descriptions',
                'description' => 'Allow the assistant to search Jira-like issues using natural-language questions.',
                'type' => 'task',
                'status' => 'review',
                'priority' => 'high',
                'estimation_type' => 'story_points',
                'story_points' => 5,
            ]),
            Ticket::query()->create([
                'project_id' => $project->id,
                'sprint_id' => $sprint->id,
                'reporter_id' => $manager->id,
                'assignee_id' => $bob->id,
                'ticket_number' => 'AIP-104',
                'title' => 'Postman API regression collection',
                'description' => 'Cover authentication, ticket CRUD, worklogs, reports and assistant endpoints.',
                'type' => 'task',
                'status' => 'todo',
                'priority' => 'medium',
                'estimation_type' => 'hours',
                'estimated_hours' => 5,
            ]),
            Ticket::query()->create([
                'project_id' => $project->id,
                'sprint_id' => $sprint->id,
                'reporter_id' => $manager->id,
                'assignee_id' => $alice->id,
                'ticket_number' => 'AIP-105',
                'title' => 'Fix overtime report edge cases',
                'description' => 'Validate overtime values and make employee and sprint calculations consistent.',
                'type' => 'bug',
                'status' => 'in_progress',
                'priority' => 'critical',
                'estimation_type' => 'story_points',
                'story_points' => 3,
            ]),
        ];

        Ticket::query()->create([
            'project_id' => $project->id,
            'sprint_id' => null,
            'reporter_id' => $manager->id,
            'assignee_id' => null,
            'ticket_number' => 'AIP-106',
            'title' => 'Prepare release documentation',
            'description' => 'Backlog issue used to demonstrate tickets that are not assigned to a sprint yet.',
            'type' => 'task',
            'status' => 'todo',
            'priority' => 'low',
            'estimation_type' => 'hours',
            'estimated_hours' => 3,
        ]);

        $logs = [
            [$tickets[0], $alice, '2026-10-05', 8, 0, 'Authentication and role setup'],
            [$tickets[2], $alice, '2026-10-06', 9, 1, 'Assistant search implementation'],
            [$tickets[4], $alice, '2026-10-07', 9.5, 1.5, 'Overtime analytics fixes'],
            [$tickets[1], $bob, '2026-10-05', 8, 0, 'Dashboard cards'],
            [$tickets[1], $bob, '2026-10-06', 10, 2, 'Employee analytics and charts'],
            [$tickets[3], $bob, '2026-10-07', 8.5, 0.5, 'Postman regression collection'],
        ];

        foreach ($logs as [$ticket, $user, $date, $hours, $overtime, $note]) {
            Worklog::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'work_date' => $date,
                'hours' => $hours,
                'overtime_hours' => $overtime,
                'note' => $note,
            ]);
        }

        Comment::query()->create([
            'ticket_id' => $tickets[2]->id,
            'user_id' => $manager->id,
            'body' => 'Search should work across both title and description.',
        ]);

        Comment::query()->create([
            'ticket_id' => $tickets[1]->id,
            'user_id' => $bob->id,
            'body' => 'Dashboard cards are connected. I am finishing the employee workload view next.',
        ]);
    }
}
