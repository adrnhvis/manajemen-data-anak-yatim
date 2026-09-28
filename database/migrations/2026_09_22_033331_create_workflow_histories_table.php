<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('application_id')->constrained('applications')->cascadeOnDelete();
            $table->enum('from_status', [
                'draft',
                'submitted',
                'revision_required',
                'validated',
                'verified',
                'rejected',
            ])->nullable();
            $table->enum('to_status', [
                'draft',
                'submitted',
                'revision_required',
                'validated',
                'verified',
                'rejected',
            ]);
            $table->string('action', 50);
            $table->text('note')->nullable();
            $table->foreignUlid('acted_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('acted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_histories');
    }
};
