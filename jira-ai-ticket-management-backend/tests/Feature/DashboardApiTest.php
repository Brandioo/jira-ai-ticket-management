<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProjectData;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProjectData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectData();
        $this->createWorklogs();
    }

    public function test_manager_dashboard_contains_team_analytics(): void
    {
        $this->withHeaders($this->authHeaders($this->manager))
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('ticket_counts.total', 1)
            ->assertJsonPath('analytics.overtime_hours', 2);
    }

    public function test_employee_dashboard_contains_own_analytics(): void
    {
        $this->withHeaders($this->authHeaders($this->alice))
            ->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('analytics.user_id', $this->alice->id)
            ->assertJsonPath('my_open_tickets', 1);
    }
}
