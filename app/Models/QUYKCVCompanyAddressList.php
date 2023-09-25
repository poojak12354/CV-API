<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;

class QUYKCVCompanyAddressList extends Model implements Searchable
{
    use HasFactory;

    protected $fillable = ['user_id','company_name','additional_information','street','street_number','zip_code','city','country'];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    protected $table = 'quyk_cv_company_list';
    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at'];

    public function getSearchResult(): SearchResult
    {
        return new SearchResult($this,$this->company_name);
    }
}
