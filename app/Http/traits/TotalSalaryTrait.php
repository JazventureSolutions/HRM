<?php

namespace App\Http\traits;

Trait TotalSalaryTrait {

	public function totalSalary($employee, $payslip_type , $basic_salary, $allowance_amount, $deduction_amount, $pension_amount, $total = 1, $total_overtime = 1){

		if($payslip_type == 'Monthly')
		{
			$total = ($basic_salary * (
					count($employee->employeeAttendance->toArray()) + 
					count($employee->officeShiftMany->toArray()) - 
					(count($employee->officeShiftSandWich->toArray())  * 3)
				)) + $allowance_amount + $employee->commissions->sum('commission_amount') - $employee->loans->sum('monthly_payable') - $deduction_amount - $pension_amount + $employee->otherPayments->sum('other_payment_amount') + ($total_overtime * ($employee->salaryBasic['overtime_rate'] * ($basic_salary / 8)));
		}
		else
		{
			$total =  ($basic_salary * $total) + $allowance_amount +  $employee->commissions->sum('commission_amount')
			- $employee->loans->sum('monthly_payable') - $deduction_amount - $pension_amount + $employee->otherPayments->sum('other_payment_amount') + ($total_overtime * ($employee->salaryBasic['overtime_rate'] * $basic_salary));
		}

        if($total<0)
        {
            $total=0;
        }
		
		return $total;
	}
}