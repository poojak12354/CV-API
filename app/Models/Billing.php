<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Billing extends Model
{
    use HasFactory;

    protected $fillable = ['cv_id','user_id','billing_start_date','billing_end_date','billing_days','billing_month','billing_amount','cv_status'];

    protected $table = 'billings';
}
