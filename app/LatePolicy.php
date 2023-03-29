<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LatePolicy extends Model
{
    protected $fillable = [
        'id','first_late','second_late','first_deduction','second_deduction','company_id','added_by'
    ];

    public function company(){
        return $this->hasOne('App\company','id','company_id');
    }

    public function getCreatedAtAttribute($value)
    {
        return Carbon::parse($value)->format(config('app.Date_Format'));
    }
}
