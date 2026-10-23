<?php

namespace Database\Seeders;

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
use Carbon\Carbon;
use Database\Seeders\Support\SimulatedWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DATA SIMULASI untuk menguji alur K-Means (BUKAN data anak yatim yang sebenarnya).
 *
 * Membuat struktur wilayah Pelalawan (12 kecamatan, 14 kelurahan, 104 desa dengan NAMA FIKTIF)
 * beserta pengajuan dan riwayat workflow berpola. Semua anak diberi NIK berawalan 9999 sehingga
 * mudah dikenali, dan `clustering:run` otomatis menandai hasilnya sebagai SIMULASI.
 *
 * Jalankan manual saja (tidak termasuk DatabaseSeeder):
 *   php artisan db:seed --class=WorkflowSimulationSeeder
 */
class WorkflowSimulationSeeder extends Seeder
{
    public const NIK_PREFIX = '9999';

    public const NAME_PREFIX = 'Anak Simulasi ';

    /** Penanda ganda (NIK dan nama) agar data lain berawalan 9999 tidak salah dikira simulasi. */
    public static function exists(): bool
    {
        return Child::withTrashed()
            ->where('nik', 'like', self::NIK_PREFIX.'%')
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->exists();
    }

    /** Kecamatan Pelalawan => [jumlah wilayah, jumlah kelurahan di dalamnya]. Total 118 wilayah, 14 kelurahan. */
    private const DISTRICTS = [
        'Bandar Petalangan' => [8, 1],
        'Bandar Sei Kijang' => [8, 1],
        'Bunut' => [12, 1],
        'Kerumutan' => [10, 1],
        'Kuala Kampar' => [9, 1],
        'Langgam' => [11, 1],
        'Pangkalan Kerinci' => [6, 3],
        'Pangkalan Kuras' => [11, 1],
        'Pangkalan Lesung' => [9, 1],
        'Pelalawan' => [11, 1],
        'Teluk Meranti' => [10, 1],
        'Ukui' => [13, 1],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Seeder simulasi tidak boleh dijalankan di production.');

            return;
        }

        if (self::exists()) {
            $this->command?->warn('Data simulasi sudah ada, dilewati.');

            return;
        }

        mt_srand(2026); // hasil selalu sama setiap dijalankan

        DB::transaction(function () {
            $actor = User::query()->where('email', 'simulasi@example.test')->first()
                ?? User::factory()->create(['name' => 'Aktor Simulasi', 'email' => 'simulasi@example.test']);

            $province = Province::firstOrCreate(['code' => '14'], ['name' => 'Riau']);
            $regency = Regency::firstOrCreate(['code' => '14.05'], ['province_id' => $province->id, 'name' => 'Pelalawan']);

            $now = now()->timestamp;
            $seq = 0;
            $villageNo = 0;
            $stats = ['villages' => 0, 'applications' => 0];

            foreach (array_keys(self::DISTRICTS) as $i => $districtName) {
                [$total, $kelurahan] = self::DISTRICTS[$districtName];
                $district = District::firstOrCreate(
                    ['regency_id' => $regency->id, 'name' => $districtName],
                    ['code' => '14.05.'.(10 + $i)],
                );

                for ($v = 0; $v < $total; $v++) {
                    $villageNo++;
                    $isKelurahan = $v < $kelurahan;
                    $village = Village::create([
                        'district_id' => $district->id,
                        'code' => sprintf('%s.9%03d', $district->code, $villageNo),
                        'name' => sprintf('%s Simulasi %03d', $isKelurahan ? 'Kelurahan' : 'Desa', $villageNo),
                    ]);
                    $stats['villages']++;

                    $profile = SimulatedWorkflow::pickProfile();
                    $factor = SimulatedWorkflow::villageFactor();

                    for ($a = SimulatedWorkflow::applicationCount(); $a > 0; $a--) {
                        $seq++;
                        $createdAt = $now - mt_rand(40, 330) * 86400;
                        $result = SimulatedWorkflow::events($profile, $factor, $createdAt, $now);
                        $this->storeApplication($village, $actor, $seq, $createdAt, $result);
                        $stats['applications']++;
                    }
                }
            }

            $this->command?->info("Simulasi dibuat: {$stats['villages']} wilayah, {$stats['applications']} pengajuan (NIK berawalan ".self::NIK_PREFIX.').');
        });
    }

    /** @param array{events: list<array<string, mixed>>, status: string} $result */
    private function storeApplication(Village $village, User $actor, int $seq, int $createdAt, array $result): void
    {
        $number = self::NIK_PREFIX.sprintf('%012d', $seq);
        $household = Household::create(['kk_number' => $number]);
        $address = Address::create([
            'village_id' => $village->id,
            'rt' => sprintf('%02d', mt_rand(1, 9)),
            'rw' => sprintf('%02d', mt_rand(1, 5)),
            'address_detail' => 'Alamat simulasi '.$seq,
            'living_with' => collect(LivingWith::cases())->random(),
        ]);
        $child = Child::create([
            'household_id' => $household->id,
            'address_id' => $address->id,
            'name' => self::NAME_PREFIX.$seq,
            'nik' => $number,
            'birth_place' => 'Pelalawan',
            'birth_date' => Carbon::now()->subYears(mt_rand(1, 17))->subDays(mt_rand(0, 364))->toDateString(),
            'gender' => mt_rand(0, 1) ? Gender::MALE : Gender::FEMALE,
        ]);

        $events = $result['events'];
        $status = ApplicationStatus::from($result['status']);
        $timeOf = fn (string $action) => collect($events)->where('action', $action)->last()['at'] ?? null;
        $lastAt = $events === [] ? $createdAt : end($events)['at'];

        $application = Application::unguarded(fn () => Application::create([
            'child_id' => $child->id,
            'period_year' => (int) now()->year,
            'status' => $status,
            'current_review_level' => match ($status) {
                ApplicationStatus::DRAFT, ApplicationStatus::REVISION_REQUIRED => ReviewLevel::VILLAGE,
                ApplicationStatus::SUBMITTED => ReviewLevel::DISTRICT,
                ApplicationStatus::VALIDATED => ReviewLevel::REGENCY,
                default => null,
            },
            'created_by' => $actor->id,
            'submitted_at' => $timeOf('submit') ? Carbon::createFromTimestamp(collect($events)->where('action', 'submit')->first()['at']) : null,
            'validated_at' => $timeOf('validate') ? Carbon::createFromTimestamp($timeOf('validate')) : null,
            'verified_at' => $timeOf('verify') ? Carbon::createFromTimestamp($timeOf('verify')) : null,
            'created_at' => Carbon::createFromTimestamp($createdAt),
            'updated_at' => Carbon::createFromTimestamp($lastAt),
        ]));

        foreach ($events as $event) {
            $at = Carbon::createFromTimestamp($event['at']);
            WorkflowHistory::unguarded(fn () => WorkflowHistory::create([
                'application_id' => $application->id,
                'from_status' => $event['from'],
                'to_status' => $event['to'],
                'action' => $event['action'],
                'note' => $event['note'],
                'acted_by' => $actor->id,
                'acted_at' => $at,
                'created_at' => $at,
                'updated_at' => $at,
            ]));
        }
    }
}
