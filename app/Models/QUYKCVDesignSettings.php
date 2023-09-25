<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QUYKCVDesignSettings extends Model
{
    use HasFactory;

    protected $fillable = ['user_id','meta_key','meta_value'];

    protected $table = 'quyk_cv_design_settings';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    
}
