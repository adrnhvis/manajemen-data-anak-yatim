<?php

namespace Tests\Feature;

use App\Enums\BankAccountHolderType;
use App\Enums\Gender;
use App\Enums\LivingWith;
use App\Enums\OrganizationUnitType;
use App\Enums\PersonStatus;
use App\Filament\Resources\Children\Pages\CreateChild;
use App\Filament\Resources\Children\Pages\EditChild;
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
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChildDocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_kelurahan_admin_can_create_child_without_documents(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Pangkalan Kerinci Kota', 'code' => '1405011001']);

        $orgUnit = OrganizationUnit::create([
            'name' => 'Kelurahan Kerinci Kota',
            'type' => OrganizationUnitType::VILLAGE,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'is_active' => true,
        ]);

        $adminKelurahan = User::factory()->create(['organization_unit_id' => $orgUnit->id]);
        $adminKelurahan->assignRole('admin_kelurahan');

        $household = Household::create(['kk_number' => '1405010000000001']);
        $address = Address::create([
            'village_id' => $village->id,
            'rt' => '01',
            'rw' => '01',
            'address_detail' => 'Jl. Utama',
            'living_with' => LivingWith::PARENTS,
        ]);

        $this->actingAs($adminKelurahan);

        Livewire::test(CreateChild::class)
            ->fillForm([
                'household_id' => $household->id,
                'address_id' => $address->id,
                'name' => 'Budi Pratama',
                'nik' => '1405010101010099',
                'birth_place' => 'Pelalawan',
                'birth_date' => '2017-01-01',
                'gender' => Gender::MALE->value,
                'father_name' => 'Ayah Budi',
                'father_status' => PersonStatus::DECEASED->value,
                'mother_name' => 'Ibu Budi',
                'mother_status' => PersonStatus::ALIVE->value,
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Pratama',
                'holder_type' => BankAccountHolderType::CHILD->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('children', [
            'nik' => '1405010101010099',
            'name' => 'Budi Pratama',
        ]);
    }

    public function test_kelurahan_admin_can_create_child_with_uploaded_document_state(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Pangkalan Kerinci Kota', 'code' => '1405011001']);

        $orgUnit = OrganizationUnit::create([
            'name' => 'Kelurahan Kerinci Kota',
            'type' => OrganizationUnitType::VILLAGE,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'is_active' => true,
        ]);

        $adminKelurahan = User::factory()->create(['organization_unit_id' => $orgUnit->id]);
        $adminKelurahan->assignRole('admin_kelurahan');

        $household = Household::create(['kk_number' => '1405010000000001']);
        $address = Address::create([
            'village_id' => $village->id,
            'rt' => '01',
            'rw' => '01',
            'address_detail' => 'Jl. Utama',
            'living_with' => LivingWith::PARENTS,
        ]);

        $category = DocumentCategory::create([
            'code' => 'surat_permohonan_pengajuan',
            'name' => 'Surat Permohonan Pengajuan',
            'is_required' => true,
            'allowed_mime_types' => ['application/pdf'],
            'max_file_size_kb' => 2048,
        ]);

        Storage::fake('local');
        Storage::disk('local')->put('temp-uploads/permohonan.pdf', '%PDF-1.4 test');

        $this->actingAs($adminKelurahan);

        Livewire::test(CreateChild::class)
            ->fillForm([
                'household_id' => $household->id,
                'address_id' => $address->id,
                'name' => 'Budi Pratama',
                'nik' => '1405010101010099',
                'birth_place' => 'Pelalawan',
                'birth_date' => '2017-01-01',
                'gender' => Gender::MALE->value,
                'father_name' => 'Ayah Budi',
                'father_status' => PersonStatus::DECEASED->value,
                'mother_name' => 'Ibu Budi',
                'mother_status' => PersonStatus::ALIVE->value,
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Pratama',
                'holder_type' => BankAccountHolderType::CHILD->value,
                'document_file_'.$category->id => ['temp-uploads/permohonan.pdf'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('children', [
            'nik' => '1405010101010099',
        ]);

        $child = Child::where('nik', '1405010101010099')->firstOrFail();
        $application = Application::where('child_id', $child->id)->firstOrFail();

        $this->assertDatabaseHas('bank_accounts', [
            'application_id' => $application->id,
            'account_number' => '1234567890',
            'account_holder_name' => 'Budi Pratama',
        ]);
        $this->assertDatabaseHas('documents', [
            'document_category_id' => $category->id,
            'application_id' => $application->id,
        ]);

        $document = Document::where('application_id', $application->id)->firstOrFail();
        $this->assertTrue(Storage::disk('local')->exists($document->storage_path));

        Storage::disk('local')->put('temp-uploads/permohonan-replacement.pdf', '%PDF-1.4 replacement');

        Livewire::test(EditChild::class, ['record' => $child->getRouteKey()])
            ->fillForm([
                'account_number' => '9876543210',
                'account_holder_name' => 'Budi Pratama Baru',
                'document_file_'.$category->id => ['temp-uploads/permohonan-replacement.pdf'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('bank_accounts', [
            'application_id' => $application->id,
            'account_number' => '9876543210',
            'account_holder_name' => 'Budi Pratama Baru',
        ]);

        $document->refresh();
        $this->assertSame(2, DocumentVersion::where('document_id', $document->id)->count());
        $this->assertTrue(Storage::disk('local')->exists($document->storage_path));
    }

    public function test_kelurahan_admin_can_create_child_with_all_eleven_document_categories(): void
    {
        $province = Province::create(['name' => 'Riau', 'code' => '14']);
        $regency = Regency::create(['province_id' => $province->id, 'name' => 'Pelalawan', 'code' => '1405']);
        $district = District::create(['regency_id' => $regency->id, 'name' => 'Pangkalan Kerinci', 'code' => '140501']);
        $village = Village::create(['district_id' => $district->id, 'name' => 'Pangkalan Kerinci Kota', 'code' => '1405011001']);

        $orgUnit = OrganizationUnit::create([
            'name' => 'Kelurahan Kerinci Kota',
            'type' => OrganizationUnitType::VILLAGE,
            'regency_id' => $regency->id,
            'district_id' => $district->id,
            'village_id' => $village->id,
            'is_active' => true,
        ]);

        $adminKelurahan = User::factory()->create(['organization_unit_id' => $orgUnit->id]);
        $adminKelurahan->assignRole('admin_kelurahan');

        $household = Household::create(['kk_number' => '1405010000000001']);
        $address = Address::create([
            'village_id' => $village->id,
            'rt' => '01',
            'rw' => '01',
            'address_detail' => 'Jl. Utama',
            'living_with' => LivingWith::PARENTS,
        ]);

        $names = [
            'Fotocopy Akte Kelahiran',
            'Fotocopy Akte Kematian Orangtua',
            'Fotocopy KTP Orang Tua/Wali',
            'Fotocopy Kartu Keluarga',
            'Fotocopy Rekening Bank Riau Kepri Syariah',
            'Pas Foto Ukuran 3x4 (Warna)',
            'Surat Permohonan Pencairan',
            'Surat Permohonan Pengajuan',
            'Surat Pernyataan Benar Permohonan Anak Yatim',
            'Surat Pernyataan Kebenaran Dokumen',
            'Surat Pernyataan Tanggungjawab Penggunaan Belanja Bantuan Sosial',
        ];

        Storage::fake('local');

        $documentFiles = [];
        foreach ($names as $index => $name) {
            $isImage = $index === 5;
            $extension = $isImage ? 'jpg' : 'pdf';
            $contents = $isImage ? "\xFF\xD8\xFF\xE0 test-image" : '%PDF-1.4 test-document';

            $category = DocumentCategory::create([
                'code' => 'cat_'.$index,
                'name' => $name,
                'is_required' => true,
                'allowed_mime_types' => $isImage ? ['image/jpeg'] : ['application/pdf'],
                'max_file_size_kb' => 2048,
            ]);

            $path = 'temp-uploads/doc-'.$index.'.'.$extension;
            Storage::disk('local')->put($path, $contents);

            $documentFiles['document_file_'.$category->id] = [$path];
        }

        $this->actingAs($adminKelurahan);

        Livewire::test(CreateChild::class)
            ->fillForm([
                'household_id' => $household->id,
                'address_id' => $address->id,
                'name' => 'Budi Pratama',
                'nik' => '1405010101010099',
                'birth_place' => 'Pelalawan',
                'birth_date' => '2017-01-01',
                'gender' => Gender::MALE->value,
                'father_name' => 'Ayah Budi',
                'father_status' => PersonStatus::DECEASED->value,
                'mother_name' => 'Ibu Budi',
                'mother_status' => PersonStatus::ALIVE->value,
                'account_number' => '1234567890',
                'account_holder_name' => 'Budi Pratama',
                'holder_type' => BankAccountHolderType::CHILD->value,
                ...$documentFiles,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('children', [
            'nik' => '1405010101010099',
        ]);
        $this->assertSame(11, Document::query()->count());
    }
}
