<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OllamaService
{
    public function enabled(): bool
    {
        return (bool) config('services.ollama.enabled', false);
    }

    public function answer(string $question, array $context): ?string
    {
        if (!$this->enabled()) {
            return null;
        }

        try {
            $response = Http::timeout(25)->post(
                rtrim((string) config('services.ollama.url'), '/').'/api/generate',
                [
                    'model' => config('services.ollama.model'),
                    'stream' => false,
                    'prompt' => "You are a Jira project assistant. Answer only from the JSON context.\n".
                        "Question: {$question}\nContext: ".json_encode($context, JSON_PRETTY_PRINT),
                ]
            );

            return $response->successful() ? $response->json('response') : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
