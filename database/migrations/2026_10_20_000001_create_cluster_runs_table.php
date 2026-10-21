<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cluster_runs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->unsignedSmallInteger('period_year')->nullable(); // null = semua tahun
            $table->unsignedTinyInteger('k');
            $table->float('silhouette');
            $table->string('quality', 20); // baik | lemah | tidak_bermakna
            $table->float('inertia');
            $table->unsignedInteger('n_clustered');
            $table->unsignedInteger('n_excluded');            // pengajuan < batas minimum
            $table->unsignedInteger('n_without_applications'); // belum pernah mengajukan
            $table->unsignedSmallInteger('min_applications');
            $table->json('feature_names');
            $table->json('centroids');
            $table->json('k_scores');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cluster_runs');
    }
};
