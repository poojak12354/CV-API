<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SingleUserAddress extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','company_name','gender','first_name','last_name','street_address','street_number','pin_code','location','country'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'id',
        'user_id',
        'created_at',
        'updated_at',
    ];

    protected $table = 'single_users_address';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    
}
