<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'api_token',
    ];

    protected $hidden = [
        'password',
        'api_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    public function reportedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'reporter_id');
    }

    public function worklogs(): HasMany
    {
        return $this->hasMany(Worklog::class);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['admin', 'manager'], true);
    }
}
