<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->enum('current_review_level', ['village', 'district', 'regency'])
                ->nullable()
                ->default('village')
                ->after('status');
        });

        DB::table('applications')
            ->where('status', 'submitted')
            ->update(['current_review_level' => 'district']);

        DB::table('applications')
            ->where('status', 'validated')
            ->update(['current_review_level' => 'regency']);

        DB::table('applications')
            ->whereIn('status', ['verified', 'rejected'])
            ->update(['current_review_level' => null]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('current_review_level');
        });
    }
};
