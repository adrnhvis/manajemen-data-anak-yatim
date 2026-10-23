<?php

namespace Tests\Feature\Filament;

use App\Enums\ApplicationStatus;
use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\ReviewLevel;
use App\Filament\Resources\Applications\Pages\ListApplications;
use App\Filament\Resources\Applications\Pages\ViewApplication;
use App\Models\Address;
use App\Models\Application;
use App\Models\Child;
use App\Models\District;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\Household;
use App\Models\OrganizationUnit;
use App\Models\Province;
use App\Models\Regency;
use App\Models\User;
use App\Models\Village;
use App\Models\WorkflowHistory;
use App\Services\DocumentChecklistService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApplicationReviewPageTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private Regency $regency;

    private District $district;

    private Village $village;

    private Village $otherVillage;

    private User $districtAdmin;

    private User $regencyAdmin;

    private Application $queued;      // menunggu kecamatan, di kecamatan ini

    private Application $handled;     // sudah dilulus-kan kecamatan ini, kini menunggu kabupaten

    private Application $outside;     // kecamatan lain

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);

        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $this->regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $this->district = District::create(['regency_id' => $this->regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $otherDistrict = District::create(['regency_id' => $this->regency->id, 'name' => 'Bandar Sei Kijang', 'code' => '140502']);
        $this->village = Village::create(['district_id' => $this->district->id, 'name' => 'Kerinci Kota', 'code' => '1405011001']);
        $this->otherVillage = Village::create(['district_id' => $otherDistrict->id, 'name' => 'Sekijang', 'code' => '1405021001']);

        $this->districtAdmin = $this->makeUser('admin_kecamatan', OrganizationUnitType::DISTRICT, $this->district);
        $this->regencyAdmin = $this->makeUser('admin_kabupaten', OrganizationUnitType::REGENCY);
        $villageAdmin = $this->makeUser('admin_kelurahan', OrganizationUnitType::VILLAGE, $this->district, $this->village);

        $this->queued = $this->makeApplication($this->village, $villageAdmin, ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT);
        $this->handled = $this->makeApplication($this->village, $villageAdmin, ApplicationStatus::VALIDATED, ReviewLevel::REGENCY);
        $this->outside = $this->makeApplication($this->otherVillage, $villageAdmin, ApplicationStatus::SUBMITTED, ReviewLevel::DISTRICT);

        WorkflowHistory::create([
            'application_id' => $this->handled->id, 'from_status' => ApplicationStatus::SUBMITTED,
            'to_status' => ApplicationStatus::VALIDATED, 'action' => 'validate',
            'acted_by' => $this->districtAdmin->id, 'acted_at' => now()->subDay(),
        ]);
    }

    public function test_district_tabs_separate_queue_history_and_scope(): void
    {
        $this->actingAs($this->districtAdmin);

        Livewire::test(ListApplications::class)
            ->assertCanSeeTableRecords([$this->queued])
            ->assertCanNotSeeTableRecords([$this->handled, $this->outside])
            ->set('activeTab', 'history')
            ->assertCanSeeTableRecords([$this->handled])
            ->assertCanNotSeeTableRecords([$this->queued, $this->outside])
            ->set('activeTab', 'all')
            ->assertCanSeeTableRecords([$this->queued, $this->handled])
            ->assertCanNotSeeTableRecords([$this->outside]);
    }

    public function test_regency_queue_only_has_validated_applications(): void
    {
        $this->actingAs($this->regencyAdmin);

        Livewire::test(ListApplications::class)
            ->assertCanSeeTableRecords([$this->handled])
            ->assertCanNotSeeTableRecords([$this->queued, $this->outside]);
    }

    public function test_review_page_denies_applications_outside_the_district(): void
    {
        $this->actingAs($this->districtAdmin);

        try {
            Livewire::test(ViewApplication::class, ['record' => $this->outside->getKey()]);
            $this->fail('Permohonan di luar kecamatan seharusnya tidak bisa dibuka.');
        } catch (ModelNotFoundException|HttpException) {
            $this->assertTrue(true);
        }
    }

    public function test_district_admin_validates_from_the_review_page(): void
    {
        $this->actingAs($this->districtAdmin);

        Livewire::test(ViewApplication::class, ['record' => $this->queued->getKey()])
            ->assertSuccessful()
            ->assertActionVisible('validate')
            ->callAction('validate')
            ->assertSuccessful();

        $this->assertSame(ApplicationStatus::VALIDATED, $this->queued->fresh()->status);
    }

    public function test_document_checklist_flags_missing_required_documents(): void
    {
        $kk = DocumentCategory::create(['code' => 'kk', 'name' => 'Kartu Keluarga', 'is_required' => true, 'allowed_mime_types' => ['application/pdf'], 'max_file_size_kb' => 2048]);
        DocumentCategory::create(['code' => 'akta', 'name' => 'Akta Kematian', 'is_required' => true, 'allowed_mime_types' => ['application/pdf'], 'max_file_size_kb' => 2048]);
        DocumentCategory::create(['code' => 'foto', 'name' => 'Foto Rumah', 'is_required' => false, 'allowed_mime_types' => ['image/jpeg'], 'max_file_size_kb' => 2048]);

        $fields = [
            'original_filename' => 'kk.pdf', 'storage_path' => 'documents/kk.pdf', 'mime_type' => 'application/pdf',
            'file_size' => 204800, 'checksum' => str_repeat('a', 64), 'uploaded_by' => $this->districtAdmin->id, 'uploaded_at' => now(),
        ];
        $document = Document::create($fields + ['application_id' => $this->queued->id, 'document_category_id' => $kk->id]);
        $version = DocumentVersion::create($fields + ['document_id' => $document->id, 'version_number' => 1]);

        $checklist = (new DocumentChecklistService)->forApplication($this->queued);

        $this->assertSame(1, $checklist['missing_required']); // akta belum ada
        $this->assertSame(['Akta Kematian', 'Kartu Keluarga', 'Foto Rumah'], array_column($checklist['items'], 'category')); // wajib dulu
        $kkItem = collect($checklist['items'])->firstWhere('category', 'Kartu Keluarga');
        $this->assertSame($version->id, $kkItem['version_id']);
        $this->assertSame('200 KB', $kkItem['size']);
    }

    private function makeUser(string $role, OrganizationUnitType $type, ?District $district = null, ?Village $village = null): User
    {
        $unit = OrganizationUnit::create([
            'name' => 'Unit '.$role, 'type' => $type,
            'regency_id' => $this->regency->id, 'district_id' => $district?->id, 'village_id' => $village?->id, 'is_active' => true,
        ]);
        $user = User::factory()->create(['organization_unit_id' => $unit->id]);
        $user->assignRole($role);

        return $user;
    }

    private function makeApplication(Village $village, User $creator, ApplicationStatus $status, ReviewLevel $level): Application
    {
        $i = ++$this->seq;
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

        return Application::create([
            'child_id' => $child->id, 'period_year' => (int) now()->year,
            'status' => $status, 'current_review_level' => $level, 'created_by' => $creator->id,
            'submitted_at' => now()->subDays(2),
        ]);
    }
}
