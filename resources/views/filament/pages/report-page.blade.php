<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-primary-200 bg-primary-50 p-4 dark:border-primary-900 dark:bg-primary-950/30">
        <div class="text-sm font-medium text-primary-700 dark:text-primary-300">Cakupan Laporan</div>
        <div class="mt-1 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $scopeDescription }}</div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Anak Terdata</div>
            <div class="mt-2 text-3xl font-bold text-primary-600 dark:text-primary-400">{{ $totalChildren }}</div>
        </div>

        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Permohonan</div>
            <div class="mt-2 text-3xl font-bold text-gray-800 dark:text-gray-100">{{ $totalApplications }}</div>
        </div>

        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Perlu Revisi</div>
            <div class="mt-2 text-3xl font-bold text-amber-500">{{ $totalRevision }}</div>
        </div>

        <div class="p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Terverifikasi Final</div>
            <div class="mt-2 text-3xl font-bold text-emerald-500">{{ $totalVerified }}</div>
        </div>
    </div>

    <div class="mt-6 p-6 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-100 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Rincian Status Permohonan Bantuan</h3>
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-center">
            <div class="p-3 bg-gray-50 dark:bg-gray-900 rounded-lg">
                <div class="text-xs text-gray-500">Draft</div>
                <div class="text-xl font-bold text-gray-700 dark:text-gray-300">{{ $totalDraft }}</div>
            </div>
            <div class="p-3 bg-blue-50 dark:bg-blue-950/30 rounded-lg">
                <div class="text-xs text-blue-600 dark:text-blue-400">Diajukan</div>
                <div class="text-xl font-bold text-blue-600 dark:text-blue-400">{{ $totalSubmitted }}</div>
            </div>
            <div class="p-3 bg-amber-50 dark:bg-amber-950/30 rounded-lg">
                <div class="text-xs text-amber-600 dark:text-amber-400">Perlu Revisi</div>
                <div class="text-xl font-bold text-amber-600 dark:text-amber-400">{{ $totalRevision }}</div>
            </div>
            <div class="p-3 bg-indigo-50 dark:bg-indigo-950/30 rounded-lg">
                <div class="text-xs text-indigo-600 dark:text-indigo-400">Validasi Kec.</div>
                <div class="text-xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalValidated }}</div>
            </div>
            <div class="p-3 bg-emerald-50 dark:bg-emerald-950/30 rounded-lg">
                <div class="text-xs text-emerald-600 dark:text-emerald-400">Terverifikasi</div>
                <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400">{{ $totalVerified }}</div>
            </div>
            <div class="p-3 bg-rose-50 dark:bg-rose-950/30 rounded-lg">
                <div class="text-xs text-rose-600 dark:text-rose-400">Ditolak</div>
                <div class="text-xl font-bold text-rose-600 dark:text-rose-400">{{ $totalRejected }}</div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
