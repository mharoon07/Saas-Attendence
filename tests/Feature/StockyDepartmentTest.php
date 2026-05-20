<?php

namespace Tests\Feature;

use App\Models\StockyUser;
use App\Models\Globals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockyDepartmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure stocky connection to use an in-memory SQLite database
        config(['database.connections.stocky' => [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]]);

        Schema::connection('stocky')->create('users', function ($table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('phone')->nullable();
            $table->integer('role_id')->nullable();
            $table->tinyInteger('is_super_admin')->default(0);
            $table->timestamps();
        });

        Schema::connection('stocky')->create('role_user', function ($table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('role_id');
            $table->timestamps();
        });

        Schema::connection('stocky')->create('departments', function ($table) {
            $table->id();
            $table->string('department');
            $table->string('code')->nullable();
            $table->integer('department_head')->nullable();
            $table->integer('user_id')->nullable();
            $table->timestamps();
        });

        // Create singleton Globals
        Globals::firstOrCreate([
            'organization_name' => 'Test Org',
            'organization_address' => 'Test Address',
            'absence_limit' => 30,
            'email' => 'test@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_guest_cannot_access_stocky_departments()
    {
        $response = $this->get('/stocky-departments');
        $response->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_stocky_departments()
    {
        $employee = StockyUser::create([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'employee@example.com',
            'password' => bcrypt('password'),
            'role_id' => 2, // Normal employee
        ]);

        $response = $this->actingAs($employee)->get('/stocky-departments');
        $response->assertStatus(403); // Forbidden by admin role middleware
    }

    public function test_admin_can_view_stocky_departments_list()
    {
        $admin = StockyUser::create([
            'firstname' => 'Admin',
            'lastname' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role_id' => 1, // Admin
        ]);

        // Seed some stocky departments
        DB::connection('stocky')->table('departments')->insert([
            [
                'department' => 'Sales & Marketing',
                'code' => 'SM01',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'department' => 'Technology',
                'code' => 'TECH02',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $response = $this->actingAs($admin)->get('/stocky-departments');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('StockyDepartment/Index')
            ->has('departments.data', 2)
            ->where('departments.data.0.department', 'Sales & Marketing')
            ->where('departments.data.1.department', 'Technology')
        );
    }

    public function test_admin_can_filter_stocky_departments_list()
    {
        $admin = StockyUser::create([
            'firstname' => 'Admin',
            'lastname' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role_id' => 1, // Admin
        ]);

        DB::connection('stocky')->table('departments')->insert([
            [
                'department' => 'Sales',
                'code' => 'SL',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'department' => 'Finance',
                'code' => 'FN',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        $response = $this->actingAs($admin)->get('/stocky-departments?term=Sales');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('StockyDepartment/Index')
            ->has('departments.data', 1)
            ->where('departments.data.0.department', 'Sales')
        );
    }
}

