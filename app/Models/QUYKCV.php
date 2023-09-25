<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;
use App\Models\Team_users as TeamUser;
use App\Models\Teams as Teams;
use App\Models\User as User;
use App\Models\QUYKCVTempPictures as QUYKCVTempPictures;



class QUYKCV extends Model implements Searchable
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['address_type','media_type','percentage','user_id','active','salutation','title','first_name','middle_name','last_name','gender','position_in_company','cv_url','cv_short_url'];

    protected $table = 'quyk_cv';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

     
   //Make it available in the json response
   protected $appends = ['edited','views','url','shorturl','user_since'];

   //implement the attribute
   public function getEditedAttribute()
   {
      return $this->percentage;
   }

   public function getViewsAttribute()
   {
      return Views::where('cv_id',$this->id)->count();
   }

   public function getUserSinceAttribute(){

      return date('m/d/Y',strtotime($this->created_at));
   }
   public function getUrlAttribute()
   {
      $user_id = $this->user_id;

      $teamUser = TeamUser::where(['team_user_id'=>$user_id])->first();

      if($teamUser){
          
         $team_url = Teams::where(['user_id'=>$teamUser->team_id])->first()->team_url;
         if($team_url){
            return config('app.frontend_url').$team_url."/".$this->cv_url;
            //return config('app.frontend_url').$this->cv_url;
         } 
      }
      return config('app.frontend_url').$this->cv_url;
   }

    public function getShorturlAttribute()
    {
      $user_id = $this->user_id;

      $teamUser = TeamUser::where(['team_user_id'=>$user_id])->first();

      if($teamUser){
          
         $team_url = Teams::where(['user_id'=>$teamUser->team_id])->first()->team_url;
         if($team_url){
            return config('app.frontend_url').$team_url."/".$this->cv_short_url;
            //return config('app.frontend_url').$this->cv_short_url;
         } 
      }
      return config('app.frontend_url').$this->cv_short_url;
    }
    /* get the user belongs to CV */

    public function user()
    {
   
    return $this->belongsTo('App\Models\User');
   
    }
    // get company addresses
    public function company_address()
    { 
       return $this->hasMany('App\Models\QUYKCVCompanyAddress','cv_id');
    }
    // get global addresses
    public function global_address()
    { 
       return $this->hasMany('App\Models\QUYKCVGlobalAddress','cv_id');
    }
    // get contact details
    public function contact_details()
    { 
       return $this->hasMany('App\Models\QUYKCVContactDetails','cv_id');
    }
     

    // get all pictures and videos
    public function pictures_videos()
    { 
      $pictures = $this->hasMany('App\Models\QUYKCVPicturesVideos','cv_id')->where('type',1)->orderBy('order','asc');
      return $pictures;
    }


    // get all pictures and videos
    public function videos()
    { 
      $videos = $this->hasMany('App\Models\QUYKCVPicturesVideos','cv_id')->where('type',2)->orderBy('order','asc');
      return $videos;
    }

     // get Social and networks
     public function social_networks()
     { 
        return $this->hasMany('App\Models\QUYKCVSocialNetworks','cv_id');
     }

     

     // get curriculum qualifications
     public function curriculum_qualifications()
     { 
        return $this->hasMany('App\Models\QUYKCVCurriculumQualifications','cv_id');
     }

     //get user temp image

     public function temp_images(){
         return $this->hasMany('App\Models\QUYKCVTempPictures','user_id','user_id')->where('type',1);
     }

      //get user temp video

      public function temp_videos(){
         return $this->hasMany('App\Models\QUYKCVTempPictures','user_id','user_id')->where('type',2);
     }
     // get search results
      public function getSearchResult(): SearchResult
      {
         return new SearchResult($this,$this->first_name);
      }

}
