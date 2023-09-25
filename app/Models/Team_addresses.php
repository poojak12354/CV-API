<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team_addresses extends Model
{
    use HasFactory;

    protected $fillable = ['team_id','company_name','address_designation','additive','road','road_no','postcode','place','country'];

    protected $table = 'team_global_addresses';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    public function user()
    {
        return $this->belongsTo('App\Models\User','team_id','id')->with('cv','user_settings'); 
    }
}

