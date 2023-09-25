<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class QUYKCVCurriculumQualifications extends Model implements Searchable
{
    use HasFactory;

    protected $fillable = ['cv_id','headline','your_text'];

    protected $table = 'quyk_cv_curriculum_qualifications';
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
        'cv_id',
        'created_at',
        'updated_at'
    ];
    
    // get search results
    public function getSearchResult(): SearchResult
    {
       return new SearchResult($this,$this->headline);
    }

     
}

