<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'project_id',
        'sprint_id',
        'reporter_id',
        'assignee_id',
        'ticket_number',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'estimation_type',
        'story_points',
        'estimated_hours',
    ];

    protected function casts(): array
    {
        return [
            'story_points' => 'integer',
            'estimated_hours' => 'float',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sprint(): BelongsTo
    {
        return $this->belongsTo(Sprint::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(Worklog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }
}
