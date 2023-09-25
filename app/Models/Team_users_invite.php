<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team_users_invite extends Model
{
    use HasFactory;

    protected $fillable = ['team_id','email','invite_code',''];

    protected $table = 'team_users_invite';
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
        'updated_at',
        'id'
    ];

    public function team()
    {
        return $this->belongsTo('App\Models\User','team_id','id'); 
    }
}

