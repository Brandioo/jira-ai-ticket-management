<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\Worklog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorklogController extends Controller
{
    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'work_date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'gt:0', 'lte:24'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'lte:hours'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();

        if (!$user->isManager() && $ticket->assignee_id !== $user->id) {
            return response()->json(['message' => 'Employees may log time only on tickets assigned to them.'], 403);
        }

        $worklog = Worklog::query()->create([
            ...$data,
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'overtime_hours' => $data['overtime_hours'] ?? 0,
        ]);

        return response()->json($worklog->load('user:id,name,email,role'), 201);
    }
}
