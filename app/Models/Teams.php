<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;

class Teams extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','company_url','company_name','team_url','team_overview','team_picture'];

    protected $table = 'quyk_cv_teams_detail';
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


    // get team users
    public function team_users()
    { 
       return $this->hasMany('App\Models\Team_users','team_id','user_id')->with('user');
    }

    // get team address
    public function team_address()
    { 
       return $this->hasMany('App\Models\Team_addresses','team_id','user_id');
    }

    protected $appends = ['picture'];

    public function getPictureAttribute()
    {
        if($this->team_picture!=null){
            return config('app.url').Storage::url($this->team_picture);
        }else{
            return null;
        }
    }
}
