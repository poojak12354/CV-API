<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use App\Permissions\HasPermissionsTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\QUYKCV;
use App\Models\Role;
use App\Models\Team_users as TeamUser;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable 
{
    use HasPermissionsTrait,HasFactory, Notifiable,HasApiTokens,SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

   protected $appends = ['slug','status','gesamthits','price','cvpages'];

    protected $fillable = [
        'first_name',
        'last_name',
        'last_name_index',
        'email',
        'password',
        'active',
        'two_factor',
        'gender'

    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'auth_code',
        'remember_token',
        'deleted_at'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    

    //implement the attribute
    public function getSlugAttribute()
    {
        $slug = $this->roles()->first()->name;
        if($slug=="basic"){$slug="Single";}
        return ucfirst($slug);
    }

    public function getGesamthitsAttribute()
    {
        return 133;
    }

    public function getPriceAttribute()
    {
        return '0,00 €';
    }

    public function getCvpagesAttribute()
    {
        if($this->roles()->first()->name=="team"){
         return TeamUser::where(array('team_id'=>$this->id))->count();
        }else{
         return QUYKCV::where('user_id',$this->id)->count();

        }
    }

    public function getStatusAttribute()
    {
        if(QUYKCV::where('user_id',$this->id)->count()>0){
            return QUYKCV::where('user_id',$this->id)->first()->active;
        }else{
            return 0;
        }
        
    }

    public function cvs()
    {

        return $this->hasMany('App\Models\QUYKCV');

    }

    public function cv()
    {

        return $this->hasOne('App\Models\QUYKCV')->with('pictures_videos','videos');

    }

    public function user_settings(){
        return $this->hasMany('App\Models\UsersSettings');

    }
    public function role() {
        return $this->hasOne(Role::class);
    }

    public function teamData()
    {
        return $this->hasOne('App\Models\Teams');
    }
}
