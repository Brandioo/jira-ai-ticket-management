<?php

namespace Tests\Feature;

use App\Models\Sprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProjectData;
use Tests\TestCase;

class AssistantActionApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProjectData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectData();
    }

    public function test_assistant_explains_how_to_create_a_new_sprint(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/assistant/query', [
                'question' => 'How can I create a new sprint please with the settled dates?',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'help');
    }

    public function test_manager_can_create_sprint_through_assistant(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/assistant/query', [
                'question' => 'Create Sprint 24 from 2026-11-02 to 2026-11-15',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'sprint_created')
            ->assertJsonPath('data.sprint.name', 'Sprint 24')
            ->assertJsonPath('data.sprint.status', 'planned');

        $this->assertDatabaseHas('sprints', [
            'project_id' => $this->project->id,
            'name' => 'Sprint 24',
            'status' => 'planned',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ]);
    }

    public function test_employee_cannot_create_sprint_through_assistant(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/assistant/query', [
                'question' => 'Create Sprint 24 from 2026-11-02 to 2026-11-15',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertForbidden();
    }

    public function test_manager_can_create_epic_through_assistant(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/assistant/query', [
                'question' => 'Create epic Customer onboarding',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'issue_created')
            ->assertJsonPath('data.ticket.type', 'epic')
            ->assertJsonPath('data.ticket.title', 'Customer onboarding');

        $this->assertDatabaseHas('tickets', [
            'project_id' => $this->project->id,
            'title' => 'Customer onboarding',
            'type' => 'epic',
        ]);
    }

    public function test_manager_can_edit_issue_fields_through_assistant(): void
    {
        $headers = $this->authHeaders($this->manager);

        $this->withHeaders($headers)
            ->postJson('/api/assistant/query', [
                'question' => 'Change TST-1 status to done',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'issue_updated')
            ->assertJsonPath('data.ticket.status', 'done');

        $this->withHeaders($headers)
            ->postJson('/api/assistant/query', [
                'question' => 'Change TST-1 type to epic',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'issue_updated')
            ->assertJsonPath('data.ticket.type', 'epic');

        $this->withHeaders($headers)
            ->postJson('/api/assistant/query', [
                'question' => 'Change TST-1 description to Updated through the AI assistant',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'issue_updated')
            ->assertJsonPath('data.ticket.description', 'Updated through the AI assistant');

        $this->assertDatabaseHas('ticket_histories', [
            'ticket_id' => $this->ticket->id,
            'field' => 'type',
            'new_value' => 'epic',
        ]);
    }

    public function test_manager_can_complete_sprint_through_assistant_and_move_unfinished_tickets(): void
    {
        $this->sprint->update([
            'name' => 'Sprint 24',
            'starts_at' => '2026-10-05',
            'ends_at' => '2026-10-18',
        ]);

        $next = Sprint::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Sprint 25',
            'status' => 'planned',
            'starts_at' => '2026-10-19',
            'ends_at' => '2026-11-01',
        ]);

        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/assistant/query', [
                'question' => 'Complete Sprint 24',
                'sprint_id' => $this->sprint->id,
            ])
            ->assertOk()
            ->assertJsonPath('mode', 'sprint_completed')
            ->assertJsonPath('data.next_sprint.id', $next->id)
            ->assertJsonPath('data.moved_tickets', 1);

        $this->assertDatabaseHas('sprints', [
            'id' => $this->sprint->id,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'sprint_id' => $next->id,
        ]);
    }
}
