<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class QUYKCVSocialNetworks extends Model implements Searchable
{
    use HasFactory;

    protected $fillable = ['cv_id','url','network','isvisible'];

    protected $table = 'quyk_cv_social_networks';
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
        'id',
        'created_at',
        'updated_at',
        'isvisible',
    ];

    // get search results
    public function getSearchResult(): SearchResult
    {
       return new SearchResult($this,$this->url);
    }

    

}
