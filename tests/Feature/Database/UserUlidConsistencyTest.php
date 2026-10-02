<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserUlidConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_permission_morph_keys_use_ulids(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin_kelurahan');

        $this->assertTrue(Str::isUlid((string) $user->getKey()));
        $this->assertContains(Schema::getColumnType('users', 'id'), ['string', 'varchar']);
        $this->assertSame('varchar', Schema::getColumnType('sessions', 'user_id'));
        $this->assertSame('varchar', Schema::getColumnType('model_has_roles', 'model_id'));
        $this->assertSame('varchar', Schema::getColumnType('model_has_permissions', 'model_id'));
        $this->assertSame((string) $user->getKey(), DB::table('model_has_roles')->value('model_id'));
        $this->assertTrue($user->hasPermissionTo('view children'));
    }

    public function test_user_schema_supports_model_active_and_soft_delete_fields(): void
    {
        $this->assertTrue(Schema::hasColumn('users', 'is_active'));
        $this->assertTrue(Schema::hasColumn('users', 'deleted_at'));

        $user = User::factory()->create(['is_active' => false]);

        $this->assertFalse($user->is_active);
        $user->delete();
        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);
    }
}
