<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockyUser extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'stocky';

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'firstname',
        'lastname',
        'username',
        'email',
        'password',
        'phone',
        'status',
        'avatar',
        'role_id',
        'is_all_warehouses',
        'is_super_admin'
    ];

    /**
     * Check if user is an Administrator or Owner in Stocky.
     *
     * @return bool
     */
    public function isAdministrator()
    {
        $roleId = $this->role_id;
        $isSuperAdmin = $this->is_super_admin;

        \Illuminate\Support\Facades\Log::info("StockyUser::isAdministrator check for user id: {$this->id}", [
            'role_id' => $roleId,
            'is_super_admin' => $isSuperAdmin
        ]);

        if ($roleId == 1 || $isSuperAdmin == 1 || $isSuperAdmin === true) {
            \Illuminate\Support\Facades\Log::info("StockyUser::isAdministrator returned true via column checks");
            return true;
        }

        // Query the roles associated via the role_user table in the stocky connection
        try {
            $hasAdminRole = \Illuminate\Support\Facades\DB::connection('stocky')
                ->table('role_user')
                ->join('roles', 'role_user.role_id', '=', 'roles.id')
                ->where('role_user.user_id', $this->id)
                ->whereIn('roles.name', ['Admin', 'Owner', 'admin', 'owner'])
                ->exists();

            \Illuminate\Support\Facades\Log::info("StockyUser::isAdministrator pivot table check result: " . ($hasAdminRole ? 'true' : 'false'));
            return $hasAdminRole;
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('StockyUser::isAdministrator role check failed: ' . $e->getMessage());
            return false;
        }
    }
}
