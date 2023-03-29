<?php

namespace App;

use Illuminate\Database\Eloquent\Model;


class office_shift extends Model
{
	use \Awobaz\Compoships\Compoships;
	
	protected $table = 'office_shifts';

    protected $fillable=['company_id', 'shift_name', 'clock_in', 'clock_out', 'shift_date', 'employee_id'];

	public function company(){
		return $this->hasOne('App\company','id','company_id');
	}

	public function employee(){
		return $this->hasOne('App\Employee','id','employee_id');
	}

	public function attendance(){
		return $this->hasOne('App\Attendance',['employee_id', 'attendance_date'], ['employee_id', 'shift_date']);
	}

	public function attendanceSandWich(){
		return $this->hasOne('App\Attendance','employee_id','employee_id');
	}
}
