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
        Schema::create('organization_units', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('parent_id')->nullable()->constrained('organization_units')->nullOnDelete();
            $table->string('name', 150);
            $table->enum('type', ['regency', 'district', 'village']);
            $table->foreignUlid('regency_id')->nullable()->constrained('regencies')->nullOnDelete();
            $table->foreignUlid('district_id')->nullable()->constrained('districts')->nullOnDelete();
            $table->foreignUlid('village_id')->nullable()->constrained('villages')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUlid('organization_unit_id')
                ->nullable()
                ->after('password')
                ->constrained('organization_units')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_unit_id');
        });

        Schema::dropIfExists('organization_units');
    }
};
