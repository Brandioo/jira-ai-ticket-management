<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProjectData;
use Tests\TestCase;

class WorklogAndReportingApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProjectData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectData();
        $this->createWorklogs();
    }

    public function test_assignee_can_log_work(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/tickets/'.$this->ticket->id.'/worklogs', [
                'work_date' => '2026-10-07',
                'hours' => 9,
                'overtime_hours' => 1,
                'note' => 'Finished API tests',
            ])
            ->assertCreated()
            ->assertJsonPath('hours', '9.00');
    }

    public function test_unassigned_employee_cannot_log_work(): void
    {
        $this->withHeaders($this->authHeaders($this->bob))
            ->postJson('/api/tickets/'.$this->ticket->id.'/worklogs', [
                'work_date' => '2026-10-07',
                'hours' => 8,
            ])
            ->assertForbidden();
    }

    public function test_overtime_cannot_exceed_total_hours(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->postJson('/api/tickets/'.$this->ticket->id.'/worklogs', [
                'work_date' => '2026-10-07',
                'hours' => 4,
                'overtime_hours' => 5,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('overtime_hours');
    }

    public function test_manager_can_read_sprint_summary(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->getJson('/api/sprints/'.$this->sprint->id.'/summary')
            ->assertOk()
            ->assertJsonPath('total_hours', 18)
            ->assertJsonPath('overtime_hours', 2)
            ->assertJsonPath('average_daily_hours', 9);
    }

    public function test_employee_cannot_read_team_sprint_summary(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->getJson('/api/sprints/'.$this->sprint->id.'/summary')
            ->assertForbidden();
    }

    public function test_employee_can_read_own_report(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->getJson('/api/reports/employees/'.$this->alice->id.'?sprint_id='.$this->sprint->id)
            ->assertOk()
            ->assertJsonPath('hours', 18)
            ->assertJsonPath('overtime_hours', 2);
    }

    public function test_employee_cannot_read_other_employee_report(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->getJson('/api/reports/employees/'.$this->bob->id.'?sprint_id='.$this->sprint->id)
            ->assertForbidden();
    }

    public function test_manager_can_read_employee_report(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->getJson('/api/reports/employees/'.$this->alice->id.'?sprint_id='.$this->sprint->id)
            ->assertOk()
            ->assertJsonPath('average_daily_hours', 9);
    }
}
