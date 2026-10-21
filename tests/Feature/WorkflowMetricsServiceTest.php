<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\ReviewLevel;
use App\Models\Address;
use App\Models\Application;
use App\Models\Child;
use App\Models\District;
use App\Models\Household;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use App\Models\WorkflowHistory;
use App\Services\WorkflowMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_village_metrics_count_returns_rejections_and_response_days(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Kerinci Kota', 'code' => '1405011001']);
        Village::create(['district_id' => $district->id, 'name' => 'Desa Kosong', 'code' => '1405011002']);
        $user = User::factory()->create();

        // Pengajuan 1: dibuat t0, diajukan t0+2h, dikembalikan 1x, dikirim ulang 3 hari kemudian.
        $t0 = now()->startOfDay()->subDays(30);
        $a1 = $this->makeApplication($village, $user, 1, $t0);
        $this->event($a1, 'submit', ApplicationStatus::DRAFT, ApplicationStatus::SUBMITTED, $t0->copy()->addDays(2));
        $this->event($a1, 'request_revision', ApplicationStatus::SUBMITTED, ApplicationStatus::REVISION_REQUIRED, $t0->copy()->addDays(3));
        $this->event($a1, 'submit', ApplicationStatus::REVISION_REQUIRED, ApplicationStatus::SUBMITTED, $t0->copy()->addDays(6));

        // Pengajuan 2: langsung diajukan hari ke-4 lalu ditolak.
        $a2 = $this->makeApplication($village, $user, 2, $t0);
        $this->event($a2, 'submit', ApplicationStatus::DRAFT, ApplicationStatus::SUBMITTED, $t0->copy()->addDays(4));
        $this->event($a2, 'reject', ApplicationStatus::SUBMITTED, ApplicationStatus::REJECTED, $t0->copy()->addDays(5));

        // Pengajuan 3: masih draft -> tidak dihitung.
        $this->makeApplication($village, $user, 3, $t0);

        $result = (new WorkflowMetricsService)->villageMetrics();

        $this->assertSame(2, $result['villages_total']);
        $this->assertCount(1, $result['rows']);

        $row = $result['rows'][0];
        $this->assertSame('Kerinci Kota', $row['village_name']);
        $this->assertSame(2, $row['applications_count']);
        $this->assertEqualsWithDelta(0.5, $row['avg_returns'], 0.0001);        // 1 revisi / 2 pengajuan
        $this->assertEqualsWithDelta(0.5, $row['reject_rate'], 0.0001);        // 1 ditolak / 2
        $this->assertEqualsWithDelta(3.0, $row['avg_resubmit_days'], 0.0001);  // 3 hari merespons revisi
        $this->assertEqualsWithDelta(3.0, $row['avg_first_submit_days'], 0.0001); // (2 + 4) / 2
    }

    private function makeApplication(Village $village, User $creator, int $i, $createdAt): Application
    {
        $household = Household::create(['kk_number' => sprintf('140500%06d', $i)]);
        $address = Address::create([
            'village_id' => $village->id, 'rt' => '01', 'rw' => '01',
            'address_detail' => 'Jl. Contoh '.$i, 'living_with' => LivingWith::PARENTS,
        ]);
        $child = Child::create([
            'household_id' => $household->id, 'address_id' => $address->id, 'name' => 'Anak '.$i,
            'nik' => sprintf('140500%010d', $i), 'birth_place' => 'Pelalawan',
            'birth_date' => '2018-01-15', 'gender' => Gender::FEMALE,
        ]);
        $application = Application::create([
            'child_id' => $child->id, 'period_year' => (int) now()->year,
            'status' => ApplicationStatus::DRAFT, 'current_review_level' => ReviewLevel::VILLAGE,
            'created_by' => $creator->id,
        ]);
        $application->forceFill(['created_at' => $createdAt])->saveQuietly();

        return $application;
    }

    private function event(Application $app, string $action, ApplicationStatus $from, ApplicationStatus $to, $at): void
    {
        WorkflowHistory::create([
            'application_id' => $app->id, 'from_status' => $from, 'to_status' => $to,
            'action' => $action, 'acted_by' => $app->created_by, 'acted_at' => $at,
        ]);
    }
}
