<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    protected function authHeaders(User $user): array
    {
        if (!$user->api_token) {
            $user->forceFill(['api_token' => Str::random(64)])->save();
        }

        return [
            'Authorization' => 'Bearer '.$user->api_token,
            'Accept' => 'application/json',
        ];
    }
}
