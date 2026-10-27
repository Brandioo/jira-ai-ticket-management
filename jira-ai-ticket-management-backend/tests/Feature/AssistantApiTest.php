<?php

namespace Tests\Feature;

use App\Models\Sprint;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProjectData;
use Tests\TestCase;

class AssistantApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProjectData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectData();
        $this->createWorklogs();
    }

    public function test_manager_can_ask_for_sprint_overtime(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/assistant/query', [
                'question' => 'How much overtime was spent in this sprint?',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'analytics')
            ->assertJsonPath('data.overtime_hours', 2);
    }

    public function test_ticket_search_wins_over_analytics_keyword(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/assistant/query', [
                'question' => 'Find tickets about overtime',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'ticket_search')
            ->assertJsonPath('data.search', 'overtime')
            ->assertJsonPath('data.tickets.0.ticket_number', 'TST-1');
    }

    public function test_employee_can_ask_for_own_average_hours(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/assistant/query', [
                'question' => 'What are my average hours?',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'employee_analytics')
            ->assertJsonPath('data.average_daily_hours', 9);
    }

    public function test_employee_cannot_query_another_employee_hours(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/assistant/query', [
                'question' => 'What are the average hours for Bob Employee?',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertForbidden();
    }

    public function test_assistant_can_list_tickets_from_explicit_sprint_name(): void
    {
        $sprint24 = Sprint::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Sprint 24',
            'status' => 'planned',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ]);

        $this->ticket->update(['sprint_id' => $sprint24->id]);

        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/assistant/query', [
                'question' => 'tickets in sprint 24',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'ticket_search')
            ->assertJsonPath('data.sprint.id', $sprint24->id)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.tickets.0.ticket_number', 'TST-1');
    }

    public function test_assistant_can_count_done_tickets(): void
    {
        Ticket::query()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $this->sprint->id,
            'reporter_id' => $this->manager->id,
            'assignee_id' => $this->bob->id,
            'ticket_number' => 'TST-2',
            'title' => 'Completed regression task',
            'type' => 'task',
            'status' => 'done',
            'priority' => 'medium',
            'estimation_type' => 'story_points',
            'story_points' => 2,
        ]);

        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/assistant/query', [
                'question' => 'How many done tickets are in Test Sprint?',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'ticket_count')
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.filters.status', 'done');
    }
}
