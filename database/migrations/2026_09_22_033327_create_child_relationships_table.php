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
        Schema::create('child_relationships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('child_id')->constrained('children')->cascadeOnDelete();
            $table->foreignUlid('person_id')->constrained('persons')->restrictOnDelete();
            $table->enum('relationship_type', ['father', 'mother', 'guardian']);
            $table->boolean('is_primary')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_relationships');
    }
};
