<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const DEPENDENT_TABLES = [
        'organization_units',
        'applications',
        'documents',
        'document_versions',
        'workflow_histories',
        'audit_logs',
        'model_has_permissions',
        'model_has_roles',
    ];

    public function up(): void
    {
        $this->ensureNoDependentTables();

        $users = DB::table('users')->orderBy('id')->get();

        Schema::rename('users', 'users_integer_backup');
        Schema::create('users_ulid', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('remember_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ($users as $user) {
            DB::table('users_ulid')->insert([
                'id' => (string) Str::ulid(),
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'remember_token' => $user->remember_token,
                'is_active' => true,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ]);
        }

        Schema::drop('users_integer_backup');
        Schema::rename('users_ulid', 'users');

        if (Schema::hasTable('sessions')) {
            Schema::drop('sessions');
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->ulid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        $this->ensureNoDependentTables();

        $users = DB::table('users')->orderBy('id')->get();

        Schema::rename('users', 'users_ulid_backup');
        Schema::create('users_integer', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        foreach ($users as $user) {
            DB::table('users_integer')->insert([
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'password' => $user->password,
                'remember_token' => $user->remember_token,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'deleted_at' => $user->deleted_at,
            ]);
        }

        Schema::drop('users_ulid_backup');
        Schema::rename('users_integer', 'users');

        if (Schema::hasTable('sessions')) {
            Schema::drop('sessions');
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    private function ensureNoDependentTables(): void
    {
        $existingTables = array_filter(
            self::DEPENDENT_TABLES,
            fn (string $table): bool => Schema::hasTable($table),
        );

        if ($existingTables !== []) {
            throw new RuntimeException(sprintf(
                'Cannot convert users.id while dependent tables already exist: %s. Use a data-preserving migration plan for this database.',
                implode(', ', $existingTables),
            ));
        }
    }
};
