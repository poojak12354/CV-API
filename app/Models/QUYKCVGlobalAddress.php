<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Searchable\Searchable;
use Spatie\Searchable\SearchResult;
use App\Models\Team_addresses as TeamAddresses;

class QUYKCVGlobalAddress extends Model implements Searchable
{
    use HasFactory;

    protected $fillable = ['cv_id','company_id'];

    protected $hidden = [
        'created_at',
        'updated_at',
        'cv_id',
        'id'
    ];

    //Make it available in the json response
   protected $appends = ['company_name',"additional_information","street","street_number","zip_code","city","country"];

    protected $table = 'cv_global_address';
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

    public function getCompanyNameAttribute()
    {
        return TeamAddresses::where('id',$this->company_id)->first()->company_name;
    }

    public function getStreetAttribute()
    {
        return TeamAddresses::where('id',$this->company_id)->first()->road;
    }

    public function getStreetNumberAttribute()
    {
        return TeamAddresses::where('id',$this->company_id)->first()->road_no;
    }

    public function getZipCodeAttribute()
    {
        return TeamAddresses::where('id',$this->company_id)->first()->postcode;
    }

    public function getCityAttribute()
    {
        return TeamAddresses::where('id',$this->company_id)->first()->place;
    }

    public function getCountryAttribute()
    {
        return TeamAddresses::where('id',$this->company_id)->first()->country;
    }

    public function getAdditionalInformationAttribute()
    {
        return TeamAddresses::where('id',$this->company_id)->first()->additive;

    }
    
}
