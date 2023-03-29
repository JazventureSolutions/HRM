<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
	use \Awobaz\Compoships\Compoships;
	protected $guarded = [];

	protected $fillable = [
		'id',
		'employee_id',
		'attendance_date',
		'day',
		'shift_name',
		'shift_in',
		'shift_out',
		'clock_in',
		'clock_in_ip',
		'clock_out',
		'clock_out_ip',
		'clock_in_out',
		'break_in',
		'break_out',
		'time_late',
		'early_leaving',
		'overtime',
		'total_work',
		'total_rest',
		'attendance_status',
		'office_shift_id',
		'is_over_time',
		'extended_attendance_date'
	];

	public $timestamps = false;

	public function employee(){
		return $this->belongsTo('App\Employee','employee_id','id');
	}

	public function singleEmployee(){
		return $this->hasOne('App\Employee','id','employee_id');
	}

	public function setAttendanceDateAttribute($value)
	{
		$this->attributes['attendance_date'] = Carbon::createFromFormat(config('app.Date_Format'), $value)->format('Y-m-d');
	}

	public function getAttendanceDateAttribute($value)
	{
		return Carbon::parse($value)->format(config('app.Date_Format'));
	}

	public function officeShift(){
		return $this->hasOne('App\office_shift','id','office_shift_id');
	}
}
