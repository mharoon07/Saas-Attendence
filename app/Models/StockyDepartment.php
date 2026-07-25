<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockyDepartment extends Model
{
    use SoftDeletes;

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
    protected $table = 'departments';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'department',
        'code',
        'department_head',
        'user_id'
    ];
}
