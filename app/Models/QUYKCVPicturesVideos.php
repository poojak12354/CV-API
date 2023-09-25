<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Storage;
class QUYKCVPicturesVideos extends Model
{
    use HasFactory;

    protected $fillable = ['file_name','cv_id','thumb','order','location','type','active'];

    protected $hidden = [
        'order'
    ];

    protected $table = 'quyk_cv_profile_pictures_videos';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    protected $appends = ['url','thumburl'];

    public function getUrlAttribute()
    {
        return config('app.url').Storage::url($this->location);
    }

    public function getThumburlAttribute()
    {
        if($this->thumb!=null)
        return config('app.url').Storage::url($this->thumb);
    }
}
