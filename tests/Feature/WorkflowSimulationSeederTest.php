<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\District;
use App\Models\Village;
use App\Services\WorkflowMetricsService;
use Database\Seeders\WorkflowSimulationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSimulationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulation_builds_pelalawan_structure_with_clearly_fake_data(): void
    {
        $this->seed(WorkflowSimulationSeeder::class);

        $this->assertSame(12, District::count());
        $this->assertSame(118, Village::count());
        $this->assertSame(14, Village::where('name', 'like', 'Kelurahan %')->count());
        $this->assertSame(104, Village::where('name', 'like', 'Desa %')->count());

        // Semua anak mudah dikenali sebagai simulasi.
        $this->assertGreaterThan(200, Child::count());
        $this->assertSame(0, Child::where('nik', 'not like', '9999%')->count());

        // Menghasilkan metrik yang cukup untuk K-Means, tapi tidak semua wilayah punya data.
        $metrics = (new WorkflowMetricsService)->villageMetrics();
        $eligible = collect($metrics['rows'])->where('applications_count', '>=', 5)->count();
        $this->assertSame(118, $metrics['villages_total']);
        $this->assertGreaterThanOrEqual(30, $eligible);
        $this->assertLessThan(118, count($metrics['rows']));
    }

    public function test_seeding_twice_does_not_duplicate(): void
    {
        $this->seed(WorkflowSimulationSeeder::class);
        $children = Child::count();

        $this->seed(WorkflowSimulationSeeder::class);

        $this->assertSame($children, Child::count());
        $this->assertSame(118, Village::count());
    }

    public function test_simulation_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->seed(WorkflowSimulationSeeder::class);
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertSame(0, Child::count());
    }
}
