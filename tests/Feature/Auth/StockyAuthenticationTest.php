<?php

namespace Tests\Feature\Auth;

use App\Models\Employee;
use App\Models\StockyUser;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
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

        // 3. Seed the Spatie roles (admin and employee) if not present
        if (!Role::where('name', 'admin')->exists()) {
            Role::create(['name' => 'admin']);
        }
        if (!Role::where('name', 'employee')->exists()) {
            Role::create(['name' => 'employee']);
        }

        // 4. Create the singleton Globals record so dashboard redirect doesn't fail
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

    public function test_standard_local_employee_can_login()
    {
        $employee = Employee::factory()->create([
            'email' => 'local@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post('/login', [
            'email' => 'local@example.com',
            'password' => 'secret123',
        ]);

        $this->assertAuthenticatedAs($employee);
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_fallback_stocky_user_can_login_and_creates_employee_and_role()
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
            'role_id' => 1, // Will map to admin role
            'is_super_admin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

         $this->assertDatabaseMissing('employees', [
            'email' => 'stocky@example.com',
        ]);

         $response = $this->post('/login', [
            'email' => 'stocky@example.com',
            'password' => 'stockySecret',
        ]);
         $this->assertAuthenticated();
    
          $employee = Employee::where('email', 'stocky@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertEquals('John Doe', $employee->name);
        $this->assertEquals('1234567890', $employee->phone);
        $this->assertEquals('STOCKY-1', $employee->national_id);
        $this->assertTrue($employee->hasRole('admin'));
        $response->assertRedirect(RouteServiceProvider::HOME);

        // Verify dashboard loads without any null property exceptions
        $dashboardResponse = $this->actingAs($employee)->get(RouteServiceProvider::HOME);
        $dashboardResponse->assertStatus(200);
    }

    public function test_fallback_stocky_user_updates_outdated_local_password_on_login()
    {
        // 1. Create a local employee with an old password
        $employee = Employee::factory()->create([
            'email' => 'sync@example.com',
            'password' => Hash::make('oldSecret'),
        ]);

        // 2. Create the same user in Stocky but with a new password
        $newStockyPassword = Hash::make('newSecret');
        DB::connection('stocky')->table('users')->insert([
            'firstname' => 'Sync',
            'lastname' => 'User',
            'email' => 'sync@example.com',
            'password' => $newStockyPassword,
            'role_id' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Perform login request with the new password
        $response = $this->post('/login', [
            'email' => 'sync@example.com',
            'password' => 'newSecret',
        ]);

        // 4. Assertions
        $this->assertAuthenticatedAs($employee);
        
        // Assert password has been synced/updated locally
        $employee->refresh();
        $this->assertTrue(Hash::check('newSecret', $employee->password));
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_fallback_stocky_user_role_syncs_for_existing_local_employee()
    {
        // 1. Create an existing employee locally with no roles assigned yet
        $employee = Employee::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('stockySecret'),
        ]);
        
        $this->assertFalse($employee->hasRole('admin'));

        // 2. Create the same user in Stocky with admin permissions (role_id => 1)
        DB::connection('stocky')->table('users')->insert([
            'firstname' => 'Existing',
            'lastname' => 'Admin',
            'email' => 'existing@example.com',
            'password' => Hash::make('stockySecret'),
            'role_id' => 1, // maps to admin
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Perform login request
        $response = $this->post('/login', [
            'email' => 'existing@example.com',
            'password' => 'stockySecret',
        ]);

        // 4. Assert role was synced to admin
        $this->assertAuthenticatedAs($employee);
        $employee->refresh();
        $this->assertTrue($employee->hasRole('admin'));
    }

    public function test_fallback_stocky_user_login_fails_with_invalid_password()
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

    public function test_fallback_stocky_user_role_syncs_via_pivot_table()
    {
        // 1. Create a user in the simulated Stocky database with a normal role_id
        $stockyPassword = Hash::make('pivotSecret');
        $userId = DB::connection('stocky')->table('users')->insertGetId([
            'firstname' => 'Pivot',
            'lastname' => 'Admin',
            'email' => 'pivot@example.com',
            'password' => $stockyPassword,
            'role_id' => 99, // generic role_id
            'is_super_admin' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Seed Spatie role 'Owner' if it doesn't exist
        $role = Role::firstOrCreate(['name' => 'Owner', 'guard_name' => 'web']);
        $roleId = $role->id;

        // Assign 'Owner' role to our stocky user via role_user table
        DB::connection('stocky')->table('role_user')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. Attempt login
        $response = $this->post('/login', [
            'email' => 'pivot@example.com',
            'password' => 'pivotSecret',
        ]);

        // 3. Assert employee was created and role 'admin' was assigned
        $this->assertAuthenticated();
        $employee = Employee::where('email', 'pivot@example.com')->first();
        $this->assertNotNull($employee);
        $this->assertTrue($employee->hasRole('admin'));
        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
