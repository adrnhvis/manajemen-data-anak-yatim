<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_results', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('cluster_run_id')->constrained('cluster_runs')->cascadeOnDelete();
            $table->foreignUlid('village_id')->constrained('villages')->restrictOnDelete();
            // Snapshot nama agar riwayat tetap utuh jika nama wilayah berubah.
            $table->string('village_name');
            $table->string('district_name');
            $table->boolean('is_clustered')->default(true);
            $table->unsignedTinyInteger('cluster_index')->nullable();
            $table->string('cluster_label')->nullable();
            $table->unsignedInteger('applications_count');
            $table->float('avg_returns');
            $table->float('reject_rate');
            $table->float('avg_resubmit_days')->nullable();
            $table->float('avg_first_submit_days');
            $table->timestamps();

            $table->unique(['cluster_run_id', 'village_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_results');
    }
};
