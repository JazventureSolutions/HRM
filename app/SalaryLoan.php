<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class SalaryLoan extends Model
{
	protected $guarded=[];

	public function employee(){
		return $this->hasOne('App\Employee','id','employee_id');
	}

	public function setStartDateAttribute($value)
	{
		$this->attributes['start_date'] = Carbon::createFromFormat(config('app.Date_Format'), $value)->format('Y-m-d');
	}

	public function getStartDateAttribute($value)
	{
		return Carbon::parse($value)->format(config('app.Date_Format'));
	}

	public function setEndDateAttribute($value)
	{
		$this->attributes['end_date'] = Carbon::createFromFormat(config('app.Date_Format'), $value)->format('Y-m-d');
	}

	public function getEndDateAttribute($value)
	{
		return Carbon::parse($value)->format(config('app.Date_Format'));
	}

}
