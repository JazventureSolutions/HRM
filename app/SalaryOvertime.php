<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SalaryOvertime extends Model
{
	protected $guarded=[];

    protected $fillable = ['employee_id','month_year','first_date','overtime_title','no_of_days','overtime_hours','overtime_rate','overtime_amount'];

	public function employee(){
		return $this->hasOne('App\Employee','id','employee_id');
	}
}