<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProjectData;
use Tests\TestCase;

class TicketApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProjectData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectData();
    }

    public function test_manager_can_create_ticket(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/tickets', [
                'project_id' => $this->project->id,
                'sprint_id' => $this->sprint->id,
                'assignee_id' => $this->bob->id,
                'title' => 'New reporting endpoint',
                'description' => 'Add sprint reporting.',
                'type' => 'story',
                'status' => 'todo',
                'priority' => 'medium',
                'story_points' => 3,
            ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'New reporting endpoint');

        $this->assertDatabaseHas('tickets', ['title' => 'New reporting endpoint']);
    }

    public function test_ticket_search_matches_title_and_description(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->getJson('/api/tickets?search=overtime')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.ticket_number', 'TST-1');
    }

    public function test_filters_by_status_and_priority(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->getJson('/api/tickets?status=in_progress&priority=high')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_assignee_can_update_ticket_status(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->putJson('/api/tickets/'.$this->ticket->id, ['status' => 'review'])
            ->assertOk()
            ->assertJsonPath('status', 'review');
    }

    public function test_unrelated_employee_cannot_update_ticket(): void
    {
        $this->withHeaders($this->authHeaders($this->bob))
            ->putJson('/api/tickets/'.$this->ticket->id, ['status' => 'done'])
            ->assertForbidden();
    }

    public function test_employee_cannot_delete_ticket(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->deleteJson('/api/tickets/'.$this->ticket->id)
            ->assertForbidden();
    }

    public function test_manager_can_delete_ticket(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->deleteJson('/api/tickets/'.$this->ticket->id)
            ->assertOk();

        $this->assertDatabaseMissing('tickets', ['id' => $this->ticket->id]);
    }

    public function test_ticket_validation_rejects_invalid_priority(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/tickets', [
                'project_id' => $this->project->id,
                'title' => 'Invalid',
                'type' => 'task',
                'status' => 'todo',
                'priority' => 'urgent-now',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');
    }

    public function test_authenticated_user_can_comment_and_status_change_creates_history(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/tickets/'.$this->ticket->id.'/comments', [
                'body' => 'I am testing the authentication fix.',
            ])
            ->assertCreated()
            ->assertJsonPath('body', 'I am testing the authentication fix.');

        $this->withHeaders($this->authHeaders($this->alice))
            ->putJson('/api/tickets/'.$this->ticket->id, ['status' => 'review'])
            ->assertOk();

        $this->assertDatabaseHas('comments', ['ticket_id' => $this->ticket->id]);
        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $this->ticket->id,
            'field' => 'status',
            'old_value' => 'in_progress',
            'new_value' => 'review',
        ]);
    }

    public function test_manager_can_create_hour_estimated_ticket_in_selected_sprint(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/tickets', [
                'project_id' => $this->project->id,
                'sprint_id' => $this->sprint->id,
                'assignee_id' => $this->alice->id,
                'title' => 'Mobile issue detail view',
                'description' => 'Add responsive detail view with activity and comments.',
                'type' => 'task',
                'status' => 'todo',
                'priority' => 'high',
                'estimation_type' => 'hours',
                'estimated_hours' => 6.5,
            ]);

        $response->assertCreated()
            ->assertJsonPath('sprint.id', $this->sprint->id)
            ->assertJsonPath('estimation_type', 'hours')
            ->assertJsonPath('estimated_hours', 6.5)
            ->assertJsonPath('story_points', null);
    }

    public function test_ticket_detail_contains_comments_worklogs_and_history(): void
    {
        $this->createWorklogs();

        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/tickets/'.$this->ticket->id.'/comments', ['body' => 'Detail page comment'])
            ->assertCreated();

        $this->withHeaders($this->authHeaders($this->alice))
            ->putJson('/api/tickets/'.$this->ticket->id, ['status' => 'review'])
            ->assertOk();

        $this->withHeaders($this->authHeaders($this->manager))
            ->getJson('/api/tickets/'.$this->ticket->id)
            ->assertOk()
            ->assertJsonPath('comments.0.body', 'Detail page comment')
            ->assertJsonCount(2, 'worklogs')
            ->assertJsonPath('histories.0.field', 'status');
    }

    public function test_manager_can_create_story_point_ticket_in_selected_sprint(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/tickets', [
                'project_id' => $this->project->id,
                'sprint_id' => $this->sprint->id,
                'assignee_id' => $this->alice->id,
                'title' => 'Story point estimate',
                'type' => 'story',
                'status' => 'todo',
                'priority' => 'medium',
                'estimation_type' => 'story_points',
                'story_points' => 8,
            ])
            ->assertCreated()
            ->assertJsonPath('sprint.id', $this->sprint->id)
            ->assertJsonPath('estimation_type', 'story_points')
            ->assertJsonPath('story_points', 8)
            ->assertJsonPath('estimated_hours', null);
    }

    public function test_ticket_cannot_be_assigned_to_sprint_from_another_project(): void
    {
        $otherProject = \App\Models\Project::query()->create([
            'key' => 'OTH',
            'name' => 'Other Project',
        ]);
        $otherSprint = \App\Models\Sprint::query()->create([
            'project_id' => $otherProject->id,
            'name' => 'Other Sprint',
            'status' => 'planned',
        ]);

        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/tickets', [
                'project_id' => $this->project->id,
                'sprint_id' => $otherSprint->id,
                'title' => 'Invalid sprint relation',
                'type' => 'task',
                'status' => 'todo',
                'priority' => 'medium',
                'estimation_type' => 'story_points',
                'story_points' => 3,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sprint_id');
    }

    public function test_manager_can_switch_estimation_from_story_points_to_hours(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->putJson('/api/tickets/'.$this->ticket->id, [
                'estimation_type' => 'hours',
                'estimated_hours' => 7.5,
            ])
            ->assertOk()
            ->assertJsonPath('estimation_type', 'hours')
            ->assertJsonPath('estimated_hours', 7.5)
            ->assertJsonPath('story_points', null);
    }


    public function test_manager_can_create_epic(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/tickets', [
                'project_id' => $this->project->id,
                'sprint_id' => $this->sprint->id,
                'title' => 'Customer onboarding epic',
                'description' => 'Group the onboarding work under one epic.',
                'type' => 'epic',
                'status' => 'todo',
                'priority' => 'high',
                'estimation_type' => 'story_points',
                'story_points' => 13,
            ])
            ->assertCreated()
            ->assertJsonPath('type', 'epic')
            ->assertJsonPath('title', 'Customer onboarding epic');
    }

    public function test_manager_can_change_issue_type_to_epic_after_creation(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->putJson('/api/tickets/'.$this->ticket->id, [
                'type' => 'epic',
            ])
            ->assertOk()
            ->assertJsonPath('type', 'epic');

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $this->ticket->id,
            'field' => 'type',
            'old_value' => 'bug',
            'new_value' => 'epic',
        ]);
    }

}
