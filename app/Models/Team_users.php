<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\UsersSettings;
use App\Models\QUYKCV;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team_users extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['team_id','team_user_id'];

    protected $table = 'team_users';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $appends = ['active','edit'];

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

    public function getActiveAttribute(){

        $data = QUYKCV::where('user_id',$this->team_user_id)->first();
        if($data){

            return (int) $data->active;
            
        }else{
            return 0;
        }
   }
   public function getEditAttribute()
   {
       
        $data = UsersSettings::where('meta_key','cv_edit_by_user')->where('user_id',$this->team_user_id)->first();
        if($data){

            return (int) $data->meta_value;
        }else{
            return 0;
        }
        
   }
    public function user()
    {
        return $this->belongsTo('App\Models\User','team_user_id','id')->with('cv','user_settings'); 
    }

     
}

