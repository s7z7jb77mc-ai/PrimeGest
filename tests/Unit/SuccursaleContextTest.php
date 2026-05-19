<?php

namespace Tests\Unit;

use App\Support\SuccursaleContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SuccursaleContextTest extends TestCase
{
    protected function tearDown(): void
    {
        Schema::clearResolvedInstances();
        session()->forget('succursale_id');

        parent::tearDown();
    }

    public function test_current_id_returns_null_when_no_active_succursale(): void
    {
        session()->forget('succursale_id');

        $this->assertNull(SuccursaleContext::currentId());
    }

    public function test_current_id_returns_integer_value(): void
    {
        session(['succursale_id' => '7']);

        $this->assertSame(7, SuccursaleContext::currentId());
    }

    public function test_should_apply_to_is_false_without_active_succursale(): void
    {
        session()->forget('succursale_id');

        $this->assertFalse(SuccursaleContext::shouldApplyTo('stocks'));
    }

    public function test_for_write_prefers_explicit_succursale_id(): void
    {
        session(['succursale_id' => 3]);

        $model = new class extends Model
        {
            protected $table = 'fake_scoped_models';
        };

        // L'ID explicite doit être retourné sans consulter le schéma
        $this->assertSame(12, SuccursaleContext::forWrite($model, 12));
    }
}
