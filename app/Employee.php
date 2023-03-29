<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;


class Employee extends Model
{
	use Notifiable;
	protected $fillable = [
		'id','first_name','last_name','nic_no', 'card_no', 'email','contact_no','date_of_birth','gender','status_id','office_shift_id','salary_id','location_id','designation_id', 'company_id', 'department_id','is_active',
		'role_users_id','permission_role_id','joining_date','exit_date','marital_status','address','city','state','country','zip_code','cv','skype_id','fb_id',
		'twitter_id','linkedIn_id','blogger_id','basic_salary','payslip_type','leave_id','attendance_id','performance_id','award_id','transfer_id','resignation_id',
		'travel_id','promotion_id','complain_id','warning_id','termination_id','attendance_type','total_leave','remaining_leave','pension_type','pension_amount'];

	public function getFullNameAttribute() {
		return ucfirst($this->first_name) . ' ' . ucfirst($this->last_name);
	}

	public function getBirthDateAttribute() {
		return $this->date_of_birth;
	}

	public function department(){
		return $this->hasOne('App\department','id','department_id');
	}

	public function officeShift(){
		return $this->belongsTo('App\office_shift','id','employee_id');
	}

	public function officeShiftMany(){
		return $this->hasMany('App\office_shift','employee_id','id');
	}

	public function officeShiftSandWich(){
		return $this->hasMany('App\office_shift','employee_id','id');
	}

	public function company(){
		return $this->hasOne('App\company','id','company_id');
	}

	public function designation(){
		return $this->hasOne('App\designation','id','designation_id');
	}

	public function status(){
		return $this->hasOne('App\status','id','status_id');
	}

	public function user(){
		return $this->hasOne('App\User','id','id');
	}

	public function role(){
		return $this->hasOne('Spatie\Permission\Models\Role','id','role_users_id');
	}

	public function salaryBasic(){
		return $this->hasOne(SalaryBasic::class);
	}

	public function allowances(){
		return $this->hasMany(SalaryAllowance::class);
	}
	public function deductions(){
		return $this->hasMany(SalaryDeduction::class);
	}
	public function commissions(){
		return $this->hasMany(SalaryCommission::class);
	}
	public function loans(){
		return $this->hasMany(SalaryLoan::class);
	}
	public function otherPayments(){
		return $this->hasMany(SalaryOtherPayment::class);
	}
	public function overtimes(){
		return $this->hasMany(SalaryOvertime::class);
	}
	public function payslips(){
		return $this->hasMany(Payslip::class);
	}

	public function payslipNew(){
		return $this->hasOne(Payslip::class);
	}

	public function employeeAttendance(){
		return $this->hasMany('App\Attendance','employee_id','id');
	}

	public function employeeOverTimeAttendance(){
		return $this->hasMany('App\Attendance','employee_id','id')->where('shift_in', "OFF");
	}

	public function employeeLeave(){
		return $this->hasMany(leave::class)
			->select('id','start_date','end_date','status','employee_id')
			->whereStatus('approved');
	}

	public function setDateOfBirthAttribute($value)
	{
		$this->attributes['date_of_birth'] = Carbon::createFromFormat(config('app.Date_Format'), $value)->format('Y-m-d');
	}

	public function getDateOfBirthAttribute($value)
	{
		return Carbon::parse($value)->format(config('app.Date_Format'));
	}

	public function setJoiningDateAttribute($value)
	{
		$this->attributes['joining_date'] = Carbon::createFromFormat(config('app.Date_Format'), $value)->format('Y-m-d');
	}

	public function getJoiningDateAttribute($value)
	{
		if($value === null)
		{
			return '';
		}
		else{
			return Carbon::parse($value)->format(config('app.Date_Format'));
		}
	}

	public function setExitDateAttribute($value)
	{
		$this->attributes['exit_date'] = Carbon::createFromFormat(config('app.Date_Format'), $value)->format('Y-m-d');
	}

	public function getExitDateAttribute($value)
	{
		if($value === null)
		{
			return '';
		}
		else{
			return Carbon::parse($value)->format(config('app.Date_Format'));
		}
	}

	public function signalSalaryBasic(){
		return $this->hasOne(SalaryBasic::class);
	}


}
