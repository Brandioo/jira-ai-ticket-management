<?php

namespace App\Services;

use App\Models\Sprint;
use App\Models\User;
use App\Models\Worklog;
use Illuminate\Support\Collection;

class WorklogAnalyticsService
{
    public function sprintSummary(Sprint $sprint): array
    {
        $worklogs = Worklog::query()
            ->with('user:id,name,email,role')
            ->whereHas('ticket', fn ($query) => $query->where('sprint_id', $sprint->id))
            ->get();

        return [
            'sprint_id' => $sprint->id,
            'sprint_name' => $sprint->name,
            'total_hours' => round((float) $worklogs->sum('hours'), 2),
            'overtime_hours' => round((float) $worklogs->sum('overtime_hours'), 2),
            'average_daily_hours' => $this->averageDailyHours($worklogs),
            'employees' => $this->groupEmployees($worklogs),
        ];
    }

    public function employeeSummary(User $user, Sprint $sprint): array
    {
        $worklogs = Worklog::query()
            ->where('user_id', $user->id)
            ->whereHas('ticket', fn ($query) => $query->where('sprint_id', $sprint->id))
            ->orderBy('work_date')
            ->get();

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'sprint_id' => $sprint->id,
            'sprint_name' => $sprint->name,
            'hours' => round((float) $worklogs->sum('hours'), 2),
            'overtime_hours' => round((float) $worklogs->sum('overtime_hours'), 2),
            'average_daily_hours' => $this->averageDailyHours($worklogs),
            'days_logged' => $worklogs->pluck('work_date')->map(fn ($date) => (string) $date)->unique()->count(),
            'entries' => $worklogs->count(),
        ];
    }

    public function averageDailyHours(Collection $worklogs): float
    {
        if ($worklogs->isEmpty()) {
            return 0.0;
        }

        $daily = $worklogs
            ->groupBy(fn ($worklog) => $worklog->work_date->format('Y-m-d'))
            ->map(fn (Collection $items) => (float) $items->sum('hours'));

        return round((float) $daily->avg(), 2);
    }

    private function groupEmployees(Collection $worklogs): array
    {
        return $worklogs
            ->groupBy('user_id')
            ->map(function (Collection $items) {
                $user = $items->first()->user;

                return [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'hours' => round((float) $items->sum('hours'), 2),
                    'overtime_hours' => round((float) $items->sum('overtime_hours'), 2),
                    'average_daily_hours' => $this->averageDailyHours($items),
                ];
            })
            ->sortByDesc('hours')
            ->values()
            ->all();
    }
}
