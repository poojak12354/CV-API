<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;

class QUYKCVTempPictures extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','file_name','location','thumb','type','temp_id'];

    protected $table = 'quyk_cv_temp_pictures';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $appends = ['url','image_id','video_id'];

    protected $hidden = [
        'user_id',
        'id',
        'created_at',
        'updated_at',
        'file_name',
        'location',
        'temp_id',
        'type',
        'thumb'
    ];

    public function getUrlAttribute()
    {
        return config('app.url').Storage::url($this->location);
    }

    public function getImageIdAttribute()
    {
        if($this->type==1){
         return $this->temp_id;  
        }else{
            return null;
        }
    }

    public function getVideoIdAttribute()
    {
        if($this->type==2){
         return $this->temp_id;  
        }else{
            return null;
        }
    }
     
}
