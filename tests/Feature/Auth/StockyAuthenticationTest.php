<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\StockyUser;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $defaultConn = config('database.default');
        $defaultConfig = config("database.connections.{$defaultConn}");
        config(['database.connections.stocky' => $defaultConfig]);

        // Share the PDO instance to avoid transaction isolation issues during testing
        DB::connection('stocky')->setPdo(DB::connection()->getPdo());
        DB::connection('stocky')->setReadPdo(DB::connection()->getPdo());

        Schema::connection('stocky')->dropIfExists('role_user');
        Schema::connection('stocky')->dropIfExists('users');

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

        // Create the singleton Globals record so dashboard redirect doesn't fail
        \App\Models\Globals::create([
            'organization_name' => 'Test Org',
            'organization_address' => 'Test Address',
            'absence_limit' => 30,
            'email' => 'test@example.com',
        ]);
    }

    protected function tearDown(): void
    {
        Schema::connection('stocky')->dropIfExists('role_user');
        Schema::connection('stocky')->dropIfExists('users');
        parent::tearDown();
    }

    public function test_stocky_user_can_login_directly_without_local_employee_duplication()
    {
        // 1. Create a user in the simulated Stocky database
        $stockyPassword = Hash::make('stockySecret');
        DB::connection('stocky')->table('users')->insert([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'username' => 'johndoe',
            'email' => 'stocky@example.com',
            'password' => $stockyPassword,
            'phone' => '1234567890',
            'role_id' => 2, // Normal employee
            'is_super_admin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assert database is empty of local employee
        $this->assertDatabaseMissing('employees', [
            'email' => 'stocky@example.com',
        ]);

        // 2. Perform login request
        $response = $this->post('/login', [
            'email' => 'stocky@example.com',
            'password' => 'stockySecret',
        ]);

        $this->assertAuthenticated();

        // 3. Assert logged-in user is an instance of StockyUser and has matching properties
        $user = auth()->user();
        $this->assertInstanceOf(StockyUser::class, $user);
        $this->assertEquals('John Doe', $user->name);

        // 4. Assert that NO employee record was created or duplicated locally
        $this->assertDatabaseMissing('employees', [
            'email' => 'stocky@example.com',
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);

        // Verify dashboard loads correctly
        $dashboardResponse = $this->actingAs($user)->get(RouteServiceProvider::HOME);
        $dashboardResponse->assertStatus(200);
    }

    public function test_stocky_admin_user_has_admin_role()
    {
        // 1. Create an admin user in simulated Stocky database
        $stockyPassword = Hash::make('adminSecret');
        DB::connection('stocky')->table('users')->insert([
            'firstname' => 'Stocky',
            'lastname' => 'Admin',
            'email' => 'admin@example.com',
            'password' => $stockyPassword,
            'role_id' => 1, // Admin in Stocky
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Perform login request
        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'adminSecret',
        ]);

        $this->assertAuthenticated();
        $user = auth()->user();
        $this->assertInstanceOf(StockyUser::class, $user);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_stocky_user_login_fails_with_invalid_password()
    {
        // 1. Create a user in the simulated Stocky database
        $stockyPassword = Hash::make('stockySecret');
        DB::connection('stocky')->table('users')->insert([
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'stocky@example.com',
            'password' => $stockyPassword,
            'role_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Perform login request with invalid password
        $response = $this->post('/login', [
            'email' => 'stocky@example.com',
            'password' => 'wrongPassword',
        ]);

        // 3. Assertions
        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }
}

