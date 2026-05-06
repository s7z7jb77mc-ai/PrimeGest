<?php

namespace Tests\Unit;

use App\Http\Middleware\CheckPlanLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class CheckPlanLimitTest extends TestCase
{
    public function test_it_allows_request_when_plan_column_is_missing(): void
    {
        Schema::shouldReceive('hasColumn')
            ->once()
            ->with('entreprises', 'plan')
            ->andReturn(false);

        $request = Request::create('/users', 'POST');

        $middleware = new CheckPlanLimit;
        $response = $middleware->handle($request, fn () => new Response('ok', 200), 'users');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }
}
