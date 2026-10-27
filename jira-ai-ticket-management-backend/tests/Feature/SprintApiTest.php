<?php

namespace Tests\Feature;

use App\Models\Sprint;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProjectData;
use Tests\TestCase;

class SprintApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProjectData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectData();
    }

    public function test_manager_can_create_sprint_with_dates(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/sprints', [
                'project_id' => $this->project->id,
                'name' => 'Sprint 24',
                'goal' => 'Regression and AI improvements',
                'starts_at' => '2026-11-02',
                'ends_at' => '2026-11-15',
            ])
            ->assertCreated()
            ->assertJsonPath('name', 'Sprint 24')
            ->assertJsonPath('status', 'planned');

        $this->assertDatabaseHas('sprints', [
            'project_id' => $this->project->id,
            'name' => 'Sprint 24',
            'status' => 'planned',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ]);
    }

    public function test_sprint_without_dates_defaults_to_fourteen_days(): void
    {
        $response = $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/sprints', [
                'project_id' => $this->project->id,
                'name' => 'Sprint 25',
            ])
            ->assertCreated();

        $id = $response->json('id');
        $created = Sprint::query()->findOrFail($id);

        $this->assertSame(
            13.0,
            Carbon::parse($created->starts_at)
                ->diffInDays(Carbon::parse($created->ends_at))
        );
    }

    public function test_manager_can_start_planned_sprint_when_no_other_sprint_is_active(): void
    {
        $this->sprint->update(['status' => 'completed']);

        $planned = Sprint::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Sprint 24',
            'status' => 'planned',
            'starts_at' => '2026-11-02',
            'ends_at' => '2026-11-15',
        ]);

        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/sprints/'.$planned->id.'/start')
            ->assertOk()
            ->assertJsonPath('status', 'active');
    }

    public function test_completing_sprint_moves_only_unfinished_tickets_to_next_sprint(): void
    {
        $this->sprint->update([
            'starts_at' => '2026-10-05',
            'ends_at' => '2026-10-18',
        ]);

        $next = Sprint::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Sprint 24',
            'status' => 'planned',
            'starts_at' => '2026-10-19',
            'ends_at' => '2026-11-01',
        ]);

        $doneTicket = Ticket::query()->create([
            'project_id' => $this->project->id,
            'sprint_id' => $this->sprint->id,
            'reporter_id' => $this->manager->id,
            'assignee_id' => $this->bob->id,
            'ticket_number' => 'TST-2',
            'title' => 'Finished ticket',
            'type' => 'task',
            'status' => 'done',
            'priority' => 'medium',
            'estimation_type' => 'story_points',
            'story_points' => 2,
        ]);

        $this->withHeaders($this->authHeaders($this->manager))
            ->postJson('/api/sprints/'.$this->sprint->id.'/complete', [
                'next_sprint_id' => $next->id,
            ])
            ->assertOk()
            ->assertJsonPath('moved_tickets', 1)
            ->assertJsonPath('next_sprint.id', $next->id);

        $this->assertDatabaseHas('tickets', [
            'id' => $this->ticket->id,
            'sprint_id' => $next->id,
        ]);

        $this->assertDatabaseHas('tickets', [
            'id' => $doneTicket->id,
            'sprint_id' => $this->sprint->id,
            'status' => 'done',
        ]);
    }

    public function test_employee_cannot_manage_sprints(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/sprints', [
                'project_id' => $this->project->id,
                'name' => 'Forbidden Sprint',
            ])
            ->assertForbidden();
    }
}
