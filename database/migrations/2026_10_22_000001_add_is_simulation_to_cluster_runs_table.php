<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cluster_runs', function (Blueprint $table) {
            // true = dihitung dari data simulasi; harus selalu terlihat jelas di Laporan.
            $table->boolean('is_simulation')->default(false)->after('period_year');
        });
    }

    public function down(): void
    {
        Schema::table('cluster_runs', function (Blueprint $table) {
            $table->dropColumn('is_simulation');
        });
    }
};
