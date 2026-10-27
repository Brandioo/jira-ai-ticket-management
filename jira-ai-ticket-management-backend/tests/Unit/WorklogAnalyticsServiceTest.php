<?php

namespace Tests\Unit;

use App\Services\WorklogAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesProjectData;
use Tests\TestCase;

class WorklogAnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesProjectData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createProjectData();
        $this->createWorklogs();
    }

    public function test_it_calculates_sprint_total_and_overtime_hours(): void
    {
        $summary = app(WorklogAnalyticsService::class)->sprintSummary($this->sprint);

        $this->assertSame(18.0, $summary['total_hours']);
        $this->assertSame(2.0, $summary['overtime_hours']);
    }

    public function test_it_calculates_average_daily_hours(): void
    {
        $summary = app(WorklogAnalyticsService::class)->employeeSummary($this->alice, $this->sprint);

        $this->assertSame(9.0, $summary['average_daily_hours']);
        $this->assertSame(2, $summary['days_logged']);
    }
}
