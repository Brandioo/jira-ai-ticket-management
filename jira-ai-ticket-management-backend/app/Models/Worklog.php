<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Worklog extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'work_date',
        'hours',
        'overtime_hours',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
