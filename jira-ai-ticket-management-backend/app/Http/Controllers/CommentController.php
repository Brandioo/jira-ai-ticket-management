<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Ticket $ticket): JsonResponse
    {
        return response()->json(
            $ticket->comments()->with('user:id,name,email,role')->latest()->get()
        );
    }

    public function store(Request $request, Ticket $ticket): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = Comment::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json($comment->load('user:id,name,email,role'), 201);
    }
}
