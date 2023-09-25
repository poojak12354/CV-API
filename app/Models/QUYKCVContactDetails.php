<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class QUYKCVContactDetails extends Model implements Searchable
{
    use HasFactory;

    protected $fillable = ['cv_id','url','isvisible','network'];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $table = 'quyk_cv_contact_details';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    // get search results
    public function getSearchResult(): SearchResult
    {
       return new SearchResult($this,$this->url);
    }

    public function getExtraDataAttribute($value)
    {   
        return json_decode($value);
    }
}
