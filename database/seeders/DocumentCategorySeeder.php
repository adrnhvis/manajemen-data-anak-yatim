<?php

namespace Database\Seeders;

use App\Models\DocumentCategory;
use Illuminate\Database\Seeder;

class DocumentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'code' => 'surat_permohonan_pengajuan',
                'name' => 'Surat Permohonan Pengajuan',
                'is_required' => true,
                'allowed_mime_types' => ['application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'surat_permohonan_pencairan',
                'name' => 'Surat Permohonan Pencairan',
                'is_required' => true,
                'allowed_mime_types' => ['application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'ktp_orangtua_wali',
                'name' => 'Fotocopy KTP Orang Tua/Wali',
                'is_required' => true,
                'allowed_mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'kartu_keluarga',
                'name' => 'Fotocopy Kartu Keluarga',
                'is_required' => true,
                'allowed_mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'akte_kelahiran',
                'name' => 'Fotocopy Akte Kelahiran / Surat Keterangan Lahir',
                'is_required' => true,
                'allowed_mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'akte_kematian_orangtua',
                'name' => 'Fotocopy Akte Kematian Orangtua / Surat Keterangan Kematian',
                'is_required' => true,
                'allowed_mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'pernyataan_benar',
                'name' => 'Surat Pernyataan Benar Permohonan Anak Yatim',
                'is_required' => true,
                'allowed_mime_types' => ['application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'pernyataan_kebenaran_dokumen',
                'name' => 'Surat Pernyataan Kebenaran Dokumen',
                'is_required' => true,
                'allowed_mime_types' => ['application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'pernyataan_tanggungjawab',
                'name' => 'Surat Pernyataan Tanggungjawab Penggunaan Belanja Bantuan Sosial',
                'is_required' => true,
                'allowed_mime_types' => ['application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'rekening_bank',
                'name' => 'Fotocopy Rekening Bank Riau Kepri Syariah',
                'is_required' => true,
                'allowed_mime_types' => ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'],
                'max_file_size_kb' => 2048,
            ],
            [
                'code' => 'pas_foto',
                'name' => 'Pas Foto Ukuran 3x4 (Warna)',
                'is_required' => true,
                'allowed_mime_types' => ['image/jpeg', 'image/jpg', 'image/png'],
                'max_file_size_kb' => 1024,
            ],
        ];

        foreach ($categories as $cat) {
            DocumentCategory::updateOrCreate(
                ['code' => $cat['code']],
                $cat
            );
        }
    }
}
