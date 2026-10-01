<?php

namespace Tests\Feature;

use App\Models\PaOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwentyKSeedBlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seed_caps_pa_volume_at_five_thousand_for_compact_demo_data(): void
    {
        putenv('DEMO_PA_COUNT=98000');
        $_ENV['DEMO_PA_COUNT'] = '98000';
        $_SERVER['DEMO_PA_COUNT'] = '98000';

        $this->seed('Database\\Seeders\\DemoDataSeeder');

        $this->assertLessThanOrEqual(5000, PaOrder::query()->count());
        $this->assertEquals(5000, PaOrder::query()->count());

        putenv('DEMO_PA_COUNT');
        unset($_ENV['DEMO_PA_COUNT'], $_SERVER['DEMO_PA_COUNT']);
    }
}
