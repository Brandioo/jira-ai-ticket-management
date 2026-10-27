<?php

namespace App\Http\Controllers;

use App\Models\Sprint;
use App\Services\AssistantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant) {}

    public function query(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:1000'],
            'sprint_id' => ['nullable', 'exists:sprints,id'],
        ]);

        $sprint = isset($data['sprint_id'])
            ? Sprint::query()->find($data['sprint_id'])
            : null;

        try {
            return response()->json(
                $this->assistant->answer($data['question'], $sprint, $request->user())
            );
        } catch (HttpException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->getStatusCode());
        }
    }
}
