<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Http\traits\MonthlyWorkedHours;
use App\Http\traits\TotalSalaryTrait;
use App\Employee;
use App\FinanceBankCash;
use App\FinanceExpense;
use App\FinanceTransaction;
use App\Payslip;
use App\SalaryBasic;
use App\SalaryLoan;
use App\company;
use Throwable;
use Exception;

class PayrollController extends Controller
{
    use TotalSalaryTrait;
    use MonthlyWorkedHours;

    public function index(Request $request)
    {

        $logged_user = auth()->user();
        $companies = company::all();
        
        $selected_date = empty($request->filter_month_year) ? now()->format('F-Y') : $request->filter_month_year;
        $first_date = date('Y-m-d', strtotime('first day of ' . $selected_date));
        $last_date = date('Y-m-d', strtotime('last day of ' . $selected_date));
        $month = date('m', strtotime($selected_date));
        $year = date('Y', strtotime($selected_date));
        
        // dd($first_date,$last_date);
        if ($logged_user->can('view-paylist')) {
            if (request()->ajax()) {
                $paid_employees = Payslip::where('month_year', $selected_date)->pluck('employee_id');
                
                $salary_basic_employees = SalaryBasic::where('first_date', '<=', $first_date)->distinct()->pluck('employee_id');
                
                $employees = Employee::with([
                    'salaryBasic' => function ($query) use ($first_date) {
                        $query->where('first_date', $first_date);
                    },
                    'allowances' => function ($query) {
                        $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                    },
                    'commissions' => function ($query) use ($first_date) {
                        $query->where('first_date', $first_date);
                    },
                    'loans' => function ($query) use ($first_date) {
                        $query->where('first_date', '<=', $first_date)
                            ->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'deductions' => function ($query) {
                            $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'otherPayments' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'overtimes' => function ($query) use ($selected_date) {
                            $query->where('month_year', $selected_date);
                        },
                    'payslips' => function ($query) use ($selected_date) {
                        $query->where('month_year', $selected_date);
                    },
                    'employeeAttendance' => function ($query) use ($first_date, $last_date) {
                        $query->where('shift_in', '!=' , "OFF")
                        ->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                    },
                    'employeeOverTimeAttendance' => function ($query) use ($first_date, $last_date) {
                        $query->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                    },
                    'officeShiftMany' => function ($query) use ($first_date, $last_date) {
                        $query->doesntHave('attendance')
                        ->where('clock_in', "OFF")
                        ->whereBetween('shift_date', [$first_date, $last_date]);
                    },
                    'officeShiftSandWich' => function ($query) use ($first_date, $last_date) {
                        $query
                        ->whereRaw("(DAYNAME(shift_date)='Saturday')")
                        ->where('clock_in', '!=' , "OFF")
                        ->whereBetween('shift_date', [$first_date, $last_date])
                        ->whereDoesntHave('attendanceSandWich', function (Builder $query2) {
                            $query2
                            ->whereIn('attendance_date',[DB::raw('office_shifts.shift_date, DATE_ADD(office_shifts.shift_date, INTERVAL 1 DAY), DATE_ADD(office_shifts.shift_date, INTERVAL 2 DAY)')]);
                        });
                    }
                ])
                ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type', 'pension_type', 'pension_amount')
                ->when($request->filter_department, function ($query) use($request) {
                    return $query->where('department_id', $request->filter_department);
                })
                ->whereHas('employeeAttendance', function (Builder $query) use ($first_date, $last_date) {
                    $query->whereBetween('attendance_date', [$first_date, $last_date]);
                })
                ->whereHas('salaryBasic', function (Builder $query) use ($first_date) {
                    $query->where('first_date', $first_date);
                })
                ->whereIn('id', $salary_basic_employees)
                ->whereNotIn('id', $paid_employees)
                ->where('is_active', 1)->where('exit_date', null)
                ->get();
                
                // dd($employees->toArray());

                try{
                    return datatables()->of($employees)
                    ->setRowId(function ($pay_list) {
                        return $pay_list->id;
                    })
                    ->addColumn('employee_name', function ($row) {
                        return $row->full_name;
                    })
                    ->addColumn('payslip_type', function ($row) {
                        $row->salaryBasic->payslip_type;
                    })
                    ->addColumn('basic_salary', function ($row) use ($first_date) {
                        return $row->salaryBasic->basic_salary;
                    })
                    ->addColumn('net_salary', function ($row) use ($first_date) {
                        //payslip_type & basic_salary

                        $payslip_type = $row->salaryBasic->payslip_type;
                        $basicsalary = $row->salaryBasic->basic_salary;

                        //Pension Amount
                        if ($row->pension_type == "percentage") {
                            $pension_amount = ($basicsalary * $row->pension_amount) / 100;
                        } else {
                            $pension_amount = $row->pension_amount;
                        }

                        $type = "getAmount";
                        $allowance_amount = $this->allowances($row, $first_date, $type);
                        $deduction_amount = $this->deductions($row, $first_date, $type);

                        $total_hours_worked = $this->totalWorkedHoursWithoutOvertime($row);

                        //New
                        $total_overtime_hours = $this->totalOvertimeHours($row);

                        $total_salary = $this->totalSalary($row, $payslip_type, $basicsalary, $allowance_amount, $deduction_amount, $pension_amount, $total_hours_worked['time'], $total_overtime_hours['time']);
                            
                        return $total_salary;
                    })
                    ->addColumn('status', function ($row) {
                        foreach ($row->payslips as $payslip) {
                            $status = $payslip->status;

                            return $status;
                        }
                    })
                    ->addColumn('action', function ($data) {
                        if (auth()->user()->can('view-paylist')) {
                            if (auth()->user()->can('make-payment')) {
                                $button = '<button type="button" name="view" id="' . $data->id . '" class="details btn btn-primary btn-sm" title="Details"><i class="dripicons-preview"></i></button>';
                                $button .= '&nbsp;&nbsp;';
                                $button .= '<button type="button" name="payment" id="' . $data->id . '" class="generate_payment btn btn-secondary btn-sm" title="generate_payment"><i class="fa fa-money"></i></button>';
                            } else {
                                $button = '';
                            }
                            return $button;
                        } else {
                            return '';
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
                } catch (Throwable $e) {
                    return abort('403', __('You are not authorized'));
                }
                
            }

            return view('salary.pay_list.index', compact('companies'));
        }

        return abort('403', __('You are not authorized'));
    }

    public function paySlip(Request $request)
    {
        $selected_date = empty($request->filter_month_year) ? now()->format('F-Y') : $request->filter_month_year;
        $month_year = $request->filter_month_year;
        $first_date = date('Y-m-d', strtotime('first day of ' . $month_year));
        $last_date = date('Y-m-d', strtotime('last day of ' . $month_year));

        $employee = Employee::with([
            'salaryBasic' => function ($query) use ($first_date) {
                $query->where('first_date', $first_date);
            },
            'allowances' => function ($query) {
                $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
            },
            'commissions' => function ($query) use ($first_date) {
                $query->where('first_date', $first_date);
            },
            'loans' => function ($query) use ($first_date) {
                $query->where('first_date', '<=', $first_date)
                    ->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
            },
            'deductions' => function ($query) {
                $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
            },
            'otherPayments' => function ($query) use ($first_date) {
                $query->where('first_date', $first_date);
            },
            'overtimes' => function ($query) use ($selected_date) {
                $query->where('month_year', $selected_date);
            },
            'payslips' => function ($query) use ($selected_date) {
                $query->where('month_year', $selected_date);
            },
            'employeeAttendance' => function ($query) use ($first_date, $last_date) {
                $query->where('shift_in', '!=' , "OFF")
                ->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
            },
            'employeeOverTimeAttendance' => function ($query) use ($first_date, $last_date) {
                $query->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
            },
            'officeShiftMany' => function ($query) use ($first_date, $last_date) {
                $query->doesntHave('attendance')
                ->where('clock_in', "OFF")
                ->whereBetween('shift_date', [$first_date, $last_date]);
            },
            'officeShiftSandWich' => function ($query) use ($first_date, $last_date) {
                $query
                ->whereRaw("(DAYNAME(shift_date)='Saturday')")
                ->where('clock_in', '!=' , "OFF")
                ->whereBetween('shift_date', [$first_date, $last_date])
                ->whereDoesntHave('attendanceSandWich', function (Builder $query2) {
                    $query2
                    ->whereIn('attendance_date',[DB::raw('office_shifts.shift_date, DATE_ADD(office_shifts.shift_date, INTERVAL 1 DAY), DATE_ADD(office_shifts.shift_date, INTERVAL 2 DAY)')]);
                });
            }
        ])
        ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type', 'pension_type', 'pension_amount', 'designation_id', 'department_id', 'joining_date')
        ->whereHas('salaryBasic', function (Builder $query) use ($first_date) {
            $query->where('first_date', $first_date);
        })
        ->findOrFail($request->id);

        $basic_salary = $employee->salaryBasic->basic_salary;
        $overtime_rate = $employee->salaryBasic->overtime_rate;
        $payslip_type = $employee->salaryBasic->payslip_type;

        //Pension Amount
        if ($employee->pension_type == "percentage") {
            $pension_amount = ($basic_salary * $employee->pension_amount) / 100.00;
        } else {
            $pension_amount = $employee->pension_amount;
        }

        $type = "getArray";
        $allowances = $this->allowances($employee, $first_date, $type);
        $deductions = $this->deductions($employee, $first_date, $type);

        $type = "getAmount";
        $allowance_amount = $this->allowances($employee, $first_date, $type);
        $deduction_amount = $this->deductions($employee, $first_date, $type);
        $allowance_amount = $this->allowances($employee, $first_date, $type);
        $deduction_amount = $this->deductions($employee, $first_date, $type);

        $data = [];
        $data['basic_salary'] = $basic_salary;
        $data['basic_total'] = $basic_salary;
        $data['allowances'] = $allowances;
        $data['commissions'] = $employee->commissions;

        $data['overtimes'] = $employee->overtimes;

        $data['loans'] = $employee->loans;
        $data['deductions'] = $deductions;
        $data['other_payments'] = $employee->otherPayments;
        $data['pension_type'] = $employee->pension_type;
        $data['pension_amount'] = $pension_amount;

        $data['employee_id'] = $employee->id;
        $data['employee_full_name'] = $employee->full_name;
        $data['employee_designation'] = $employee->designation->designation_name ?? '';
        $data['employee_department'] = $employee->department->department_name ?? '';
        $data['employee_join_date'] = $employee->joining_date;
        $data['employee_username'] = $employee->user->username;
        $data['employee_pp'] = $employee->user->profile_photo ?? '';

        $data['payslip_type'] = $payslip_type;

        $total_hours_worked = $this->totalWorkedHoursWithoutOvertime($employee);

        $data['monthly_worked_hours'] = $total_hours_worked['str'];
        $data['monthly_worked_amount'] = ($basic_salary) * $total_hours_worked['time'];

        //New
        $total_overtime_hours = $this->totalOvertimeHours($employee);

        $total_salary = $this->totalSalary($employee, $payslip_type, $basic_salary, $allowance_amount, $deduction_amount, $pension_amount, $total_hours_worked['time'], $total_overtime_hours['time']);

        // new;
        $data['totalOvertimeDetails'] = $total_overtime_hours['str'];
        $data['overtimeAmount'] = $overtime_rate;
        $data['totalOvertimeAmount'] = $total_overtime_hours['time'] * ($overtime_rate * ($basic_salary / 8));
        $data['totalMonthAmount'] = $total_salary;

        return response()->json(['data' => $data]);
    }

    public function paySlipGenerate(Request $request)
    {
        $selected_date = empty($request->filter_month_year) ? now()->format('F-Y') : $request->filter_month_year;
        $month_year = $request->filter_month_year;
        $first_date = date('Y-m-d', strtotime('first day of ' . $month_year));
        $last_date = date('Y-m-d', strtotime('last day of ' . $month_year));

        $employee = Employee::with([
            'salaryBasic' => function ($query) use ($first_date) {
                $query->where('first_date', $first_date);
            },
            'allowances' => function ($query) {
                $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
            },
            'commissions' => function ($query) use ($first_date) {
                $query->where('first_date', $first_date);
            },
            'loans' => function ($query) use ($first_date) {
                $query->where('first_date', '<=', $first_date)
                    ->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
            },
            'deductions' => function ($query) {
                $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
            },
            'otherPayments' => function ($query) use ($first_date) {
                $query->where('first_date', $first_date);
            },
            'overtimes' => function ($query) use ($selected_date) {
                $query->where('month_year', $selected_date);
            },
            'payslips' => function ($query) use ($selected_date) {
                $query->where('month_year', $selected_date);
            },
            'employeeAttendance' => function ($query) use ($first_date, $last_date) {
                $query->where('shift_in', '!=' , "OFF")
                ->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
            },
            'employeeOverTimeAttendance' => function ($query) use ($first_date, $last_date) {
                $query->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
            },
            'officeShiftMany' => function ($query) use ($first_date, $last_date) {
                $query->doesntHave('attendance')
                ->where('clock_in', "OFF")
                ->whereBetween('shift_date', [$first_date, $last_date]);
            },
            'officeShiftSandWich' => function ($query) use ($first_date, $last_date) {
                $query
                ->whereRaw("(DAYNAME(shift_date)='Saturday')")
                ->where('clock_in', '!=' , "OFF")
                ->whereBetween('shift_date', [$first_date, $last_date])
                ->whereDoesntHave('attendanceSandWich', function (Builder $query2) {
                    $query2
                    ->whereIn('attendance_date',[DB::raw('office_shifts.shift_date, DATE_ADD(office_shifts.shift_date, INTERVAL 1 DAY), DATE_ADD(office_shifts.shift_date, INTERVAL 2 DAY)')]);
                });
            }
        ])
        ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type', 'designation_id', 'department_id', 'joining_date', 'pension_type', 'pension_amount')
        ->whereHas('salaryBasic', function (Builder $query) use ($first_date) {
            $query->where('first_date', $first_date);
        })
        ->whereHas('employeeAttendance', function (Builder $query) use ($first_date, $last_date) {
            $query->whereBetween('attendance_date', [$first_date, $last_date]);
        })
        ->findOrFail($request->id);

        //payslip_type & basic_salary
        $basic_salary = $employee->salaryBasic->basic_salary;
        $overtime_rate = $employee->salaryBasic->overtime_rate;
        $payslip_type = $employee->salaryBasic->payslip_type;
    
        //Pension Amount
        if ($employee->pension_type == "percentage") {
            $pension_amount = ($basic_salary * $employee->pension_amount) / 100;
        } else {
            $pension_amount = $employee->pension_amount;
        }

        $type = "getAmount";
        $allowance_amount = $this->allowances($employee, $first_date, $type);
        $deduction_amount = $this->deductions($employee, $first_date, $type);

        $data = [];
        $data['employee'] = $employee->id;
        $data['basic_salary'] = $basic_salary;
        $data['total_allowance'] = $allowance_amount;
        $data['total_commission'] = $employee->commissions->sum('commission_amount');
        $data['monthly_payable'] = $employee->loans->sum('monthly_payable');
        $data['amount_remaining'] = $employee->loans->sum('amount_remaining');
        $data['total_deduction'] = $deduction_amount;
        
        $data['total_other_payment'] = $employee->otherPayments->sum('other_payment_amount');
        $data['payslip_type'] = $payslip_type;
        $data['pension_amount'] = $pension_amount;
            
        $totalHoursWithoutOvertime = $this->totalWorkedHoursWithoutOvertime($employee);
        $total_hours = $this->totalWorkedHours($employee);
        
        $data['total_hours'] = $total_hours['str'];

        //New
        $total_overtime_hours = $this->totalOvertimeHours($employee);

        $total_salary = $this->totalSalary($employee, $payslip_type, $basic_salary, $allowance_amount, $deduction_amount, $pension_amount, $basic_salary, $total_overtime_hours['time']);

        // new;
        $data['total_overtime_hours'] = $total_overtime_hours['str'];
        $data['overtime_rate'] = $overtime_rate;
        $data['total_overtime'] = $total_overtime_hours['time'] * ($overtime_rate * ($basic_salary / 8));
        $data['total_salary'] = $total_salary;

        return response()->json(['data' => $data]);
    }

    public function payEmployee($id, Request $request)
    {
        $logged_user = auth()->user();

        if ($logged_user->can('make-payment')) {
            $first_date = date('Y-m-d', strtotime('first day of ' . $request->month_year));

            DB::beginTransaction();
            try
            {
                $employee = Employee::with(['salaryBasic' => function ($query) use ($first_date) {
                    $query->where('first_date', $first_date);
                },
                'commissions' => function ($query) use ($first_date) {
                    $query->where('first_date', $first_date);
                },
                'loans' => function ($query) use ($first_date) {
                    $query->where('first_date', '<=', $first_date)
                        ->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                },
                'deductions' => function ($query) {
                    $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                },
                'otherPayments' => function ($query) use ($first_date) {
                    $query->where('first_date', $first_date);
                },
                'overtimes' => function ($query) use ($first_date) {
                    $query->where('first_date', $first_date);
                }])
                ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type', 'pension_type', 'pension_amount', 'company_id')
                ->whereHas('salaryBasic', function (Builder $query) use ($first_date) {
                    $query->where('first_date', $first_date);
                })
                ->findOrFail($id);

                $type = "getArray";
                $allowances = $this->allowances($employee, $first_date, $type); //getArray
                $deductions = $this->deductions($employee, $first_date, $type);

                $data = [];
                $data['payslip_key'] = Str::random('20');
                $data['payslip_number'] = mt_rand(1000000000, 9999999999);
                $data['payment_type'] = $request->payslip_type;
                $data['basic_salary'] = $request->basic_salary; //This Point
                $data['allowances'] = $allowances;
                $data['commissions'] = $employee->commissions;
                $data['loans'] = $employee->loans;
                $data['deductions'] = $deductions;
                $data['overtimes'] = $employee->overtimes;
                $data['other_payments'] = $employee->otherPayments;
                $data['month_year'] = $request->month_year;
                $data['status'] = 1;
                $data['employee_id'] = $employee->id;
                $data['hours_worked'] = $request->worked_hours;
                $data['pension_type'] = $employee->pension_type;
                $data['pension_amount'] = $request->pension_amount;
                $data['company_id'] = $employee->company_id;

                //New
                $data['advance_deduct'] = $request->advance_deduct;
                $data['punishment'] = $request->punishment;
                $data['special_allowance'] = $request->special_allowance;
                $data['net_salary'] = $request->net_salary;

                if ($data['payment_type'] == null) { //No Need This Line
                    return response()->json(['payment_type_error' => __('Please select a payslip-type for this employee.')]);
                }

                $account_balance = DB::table('finance_bank_cashes')->where('id', config('variable.account_id'))->pluck('account_balance')->first();

                if ((int) $account_balance < (int) $data['net_salary']) {
                    return response()->json(['error' => 'requested balance is less then available balance']);
                }

                $new_balance = (int) $account_balance - (int) $data['net_salary'];

                $finance_data = [];

                $finance_data['account_id'] = config('variable.account_id');
                $finance_data['amount'] = $data['net_salary'];
                $finance_data['expense_date'] = now()->format(config('app.Date_Format'));
                $finance_data['expense_reference'] = trans('file.Payroll');

                FinanceBankCash::whereId($finance_data['account_id'])->update(['account_balance' => $new_balance]);

                $Expense = FinanceTransaction::create($finance_data);

                $finance_data['id'] = $Expense->id;

                FinanceExpense::create($finance_data);

                if ($employee->loans) {
                    foreach ($employee->loans as $loan) {
                        if ($loan->time_remaining == '0') {
                            $amount_remaining = 0;
                            $time_remaining = 0;
                            $monthly_payable = 0;
                        } else {
                            $amount_remaining = $loan->amount_remaining - $loan->monthly_payable;
                            $time_remaining = $loan->time_remaining - 1;
                            $monthly_payable = $loan->monthly_payable;
                        }
                        SalaryLoan::whereId($loan->id)->update(['amount_remaining' => $amount_remaining, 'time_remaining' => $time_remaining,
                            'monthly_payable' => $monthly_payable]);
                    }
                    $employee_loan = Employee::with('loans:id,employee_id,loan_title,loan_amount,time_remaining,amount_remaining,monthly_payable')
                        ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type')
                        ->findOrFail($id);
                    $data['loans'] = $employee_loan->loans;
                }
                Payslip::create($data);

                DB::commit();

            } catch (Exception $e) {
                DB::rollback();
                return response()->json(['error' => $e->getMessage()]);
            } catch (Throwable $e) {
                DB::rollback();
                return response()->json(['error' => $e->getMessage()]);
            }

            return response()->json(['success' => __('Data Added successfully.')]);
        }

        return response()->json(['success' => __('You are not authorized')]);

    }

    //--- Updated ----
    public function payBulk(Request $request)
    {
        $logged_user = auth()->user();
        if ($logged_user->can('make-bulk_payment')) {
            if (request()->ajax()) {
                $first_date = date('Y-m-d', strtotime('first day of ' . $request->month_year));
                $last_date = date('Y-m-d', strtotime('last day of ' . $request->month_year));
                $employeeArrayId = $request->all_checkbox_id;
                //$employeesId = Employee::whereIn('id',$employeeArrayId)->whereNotIn('id',$paid_employee)->pluck('id');

                if (!empty($request->filter_company && $request->filter_department)) //No Need
                {
                    $employees = Employee::with([
                        'salaryBasic' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'allowances' => function ($query) {
                            $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'commissions' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'loans' => function ($query) use ($first_date) {
                            $query->where('first_date', '<=', $first_date)
                                ->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'deductions' => function ($query) {
                            $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'otherPayments' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'overtimes' => function ($query) use ($selected_date) {
                            $query->where('month_year', $selected_date);
                        },
                        'payslips' => function ($query) use ($selected_date) {
                            $query->where('month_year', $selected_date);
                        },
                        'employeeAttendance' => function ($query) use ($first_date, $last_date) {
                            $query->where('shift_in', '!=' , "OFF")
                            ->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                        },
                        'employeeOverTimeAttendance' => function ($query) use ($first_date, $last_date) {
                            $query->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                        },
                        'officeShiftMany' => function ($query) use ($first_date, $last_date) {
                            $query->doesntHave('attendance')
                            ->where('clock_in', "OFF")
                            ->whereBetween('shift_date', [$first_date, $last_date]);
                        },
                        'officeShiftSandWich' => function ($query) use ($first_date, $last_date) {
                            $query
                            ->whereRaw("(DAYNAME(shift_date)='Saturday')")
                            ->where('clock_in', '!=' , "OFF")
                            ->whereBetween('shift_date', [$first_date, $last_date])
                            ->whereDoesntHave('attendanceSandWich', function (Builder $query2) {
                                $query2
                                ->whereIn('attendance_date',[DB::raw('office_shifts.shift_date, DATE_ADD(office_shifts.shift_date, INTERVAL 1 DAY), DATE_ADD(office_shifts.shift_date, INTERVAL 2 DAY)')]);
                            });
                        }
                    ])
                    ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type', 'pension_type', 'pension_amount', 'company_id')
                    ->where('company_id', $request->filter_company)
                    ->where('department_id', $request->filter_department)
                    ->whereIn('id', $employeeArrayId)
                    ->where('is_active', 1)->where('exit_date', null)
                    ->whereHas('salaryBasic', function (Builder $query) use ($first_date) {
                        $query->where('first_date', $first_date);
                    })
                    ->get();
                } elseif (!empty($request->filter_company)) //No Need
                {
                    $employees = Employee::with([
                        'salaryBasic' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'allowances' => function ($query) {
                            $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'commissions' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'loans' => function ($query) use ($first_date) {
                            $query->where('first_date', '<=', $first_date)
                                ->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'deductions' => function ($query) {
                            $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'otherPayments' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'overtimes' => function ($query) use ($selected_date) {
                            $query->where('month_year', $selected_date);
                        },
                        'payslips' => function ($query) use ($selected_date) {
                            $query->where('month_year', $selected_date);
                        },
                        'employeeAttendance' => function ($query) use ($first_date, $last_date) {
                            $query->where('shift_in', '!=' , "OFF")
                            ->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                        },
                        'employeeOverTimeAttendance' => function ($query) use ($first_date, $last_date) {
                            $query->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                        },
                        'officeShiftMany' => function ($query) use ($first_date, $last_date) {
                            $query->doesntHave('attendance')
                            ->where('clock_in', "OFF")
                            ->whereBetween('shift_date', [$first_date, $last_date]);
                        },
                        'officeShiftSandWich' => function ($query) use ($first_date, $last_date) {
                            $query
                            ->whereRaw("(DAYNAME(shift_date)='Saturday')")
                            ->where('clock_in', '!=' , "OFF")
                            ->whereBetween('shift_date', [$first_date, $last_date])
                            ->whereDoesntHave('attendanceSandWich', function (Builder $query2) {
                                $query2
                                ->whereIn('attendance_date',[DB::raw('office_shifts.shift_date, DATE_ADD(office_shifts.shift_date, INTERVAL 1 DAY), DATE_ADD(office_shifts.shift_date, INTERVAL 2 DAY)')]);
                            });
                        }
                    ])
                    ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type', 'pension_type', 'pension_amount', 'company_id')
                    ->whereHas('salaryBasic', function (Builder $query) use ($first_date) {
                        $query->where('first_date', $first_date);
                    })
                    ->where('company_id', $request->filter_company)
                    ->whereIn('id', $employeeArrayId)
                    ->where('is_active', 1)->where('exit_date', null)
                    ->get();
                } else {
                    $employees = Employee::with([
                        'salaryBasic' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'allowances' => function ($query) {
                            $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'commissions' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'loans' => function ($query) use ($first_date) {
                            $query->where('first_date', '<=', $first_date)
                                ->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'deductions' => function ($query) {
                            $query->orderByRaw('DATE_FORMAT(first_date, "%y-%m")');
                        },
                        'otherPayments' => function ($query) use ($first_date) {
                            $query->where('first_date', $first_date);
                        },
                        'overtimes' => function ($query) use ($selected_date) {
                            $query->where('month_year', $selected_date);
                        },
                        'payslips' => function ($query) use ($selected_date) {
                            $query->where('month_year', $selected_date);
                        },
                        'employeeAttendance' => function ($query) use ($first_date, $last_date) {
                            $query->where('shift_in', '!=' , "OFF")
                            ->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                        },
                        'employeeOverTimeAttendance' => function ($query) use ($first_date, $last_date) {
                            $query->whereBetween('attendances.attendance_date', [$first_date, $last_date]);
                        },
                        'officeShiftMany' => function ($query) use ($first_date, $last_date) {
                            $query->doesntHave('attendance')
                            ->where('clock_in', "OFF")
                            ->whereBetween('shift_date', [$first_date, $last_date]);
                        },
                        'officeShiftSandWich' => function ($query) use ($first_date, $last_date) {
                            $query
                            ->whereRaw("(DAYNAME(shift_date)='Saturday')")
                            ->where('clock_in', '!=' , "OFF")
                            ->whereBetween('shift_date', [$first_date, $last_date])
                            ->whereDoesntHave('attendanceSandWich', function (Builder $query2) {
                                $query2
                                ->whereIn('attendance_date',[DB::raw('office_shifts.shift_date, DATE_ADD(office_shifts.shift_date, INTERVAL 1 DAY), DATE_ADD(office_shifts.shift_date, INTERVAL 2 DAY)')]);
                            });
                        }
                    ])
                    ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type', 'pension_type', 'pension_amount', 'company_id')
                    ->whereHas('salaryBasic', function (Builder $query) use ($first_date) {
                        $query->where('first_date', $first_date);
                    })
                    ->whereIn('id', $employeeArrayId)
                    ->where('is_active', 1)->where('exit_date', null)
                    ->get();
                }

                DB::beginTransaction();
                try
                {
                    $total_sum = 0;
                    foreach ($employees as $employee) {
                        
                        $payslip_type = $employee->salaryBasic->payslip_type;
                        $basicsalary = $employee->salaryBasic->basic_salary;
                         
                        //Pension Amount
                        if ($employee->pension_type == "percentage") {
                            $pension_amount = ($basicsalary * $employee->pension_amount) / 100;
                        } else {
                            $pension_amount = $employee->pension_amount;
                        }

                        $type1 = "getArray";
                        $allowances = $this->allowances($employee, $first_date, $type1); //getArray
                        $deductions = $this->deductions($employee, $first_date, $type1);

                        $type2 = "getAmount";
                        $allowance_amount = $this->allowances($employee, $first_date, $type2);
                        $deduction_amount = $this->deductions($employee, $first_date, $type2);

                        //Net Salary
                        if ($payslip_type == 'Monthly') {

                            $net_salary = $this->totalSalary($employee, $payslip_type, $basicsalary, $allowance_amount, $deduction_amount, $pension_amount);
                        } else {
                            
                            $total_hours = $this->totalWorkedHoursWithoutOvertime($employee);
                            
                            //converting in minute

                            //New
                            $total_overtime_hours = $this->totalOvertimeHours($employee);

                            // return $total_hours;
                            $net_salary = $this->totalSalary($employee, $payslip_type, $basicsalary, $allowance_amount, $deduction_amount, $pension_amount, $total_hours['time'], $total_overtime_hours['time']);
                        }

                        $data = [];
                        $data['payslip_key'] = Str::random('20');
                        $data['payslip_number'] = mt_rand(1000000000, 9999999999);
                        $data['payment_type'] = $payslip_type;
                        $data['basic_salary'] = $basicsalary; //
                        $data['allowances'] = $allowances;
                        $data['commissions'] = $employee->commissions;
                        $data['loans'] = $employee->loans;
                        $data['deductions'] = $deductions;
                        $data['overtimes'] = $employee->overtimes;
                        $data['other_payments'] = $employee->otherPayments;
                        $data['month_year'] = $request->month_year;
                        $data['net_salary'] = $net_salary;
                        $data['status'] = 1;
                        $data['employee_id'] = $employee->id;
                        $data['hours_worked'] = $total_hours; //only for Hourly base employee
                        $data['pension_type'] = $employee->pension_type;
                        $data['pension_amount'] = $pension_amount;
                        $data['company_id'] = $employee->company_id;

                        $total_sum = $total_sum + $net_salary;

                        if ($employee->loans) {
                            foreach ($employee->loans as $loan) {
                                if ($loan->time_remaining == '0') {
                                    $amount_remaining = 0;
                                    $time_remaining = 0;
                                    $monthly_payable = 0;
                                } else {
                                    $amount_remaining = $loan->amount_remaining - $loan->monthly_payable;
                                    $time_remaining = $loan->time_remaining - 1;
                                    $monthly_payable = $loan->monthly_payable;
                                }
                                SalaryLoan::whereId($loan->id)->update(['amount_remaining' => $amount_remaining, 'time_remaining' => $time_remaining,
                                    'monthly_payable' => $monthly_payable]);
                            }
                            $employee_loan = Employee::with('loans:id,employee_id,loan_title,loan_amount,time_remaining,amount_remaining,monthly_payable')
                                ->select('id', 'first_name', 'last_name', 'basic_salary', 'payslip_type')
                                ->findOrFail($employee->id);
                            $data['loans'] = $employee_loan->loans;
                        }

                        if ($data['payment_type'] == null) { //New
                            return response()->json(['payment_type_error' => __('Please select payslip-type for the employees.')]);
                        }

                        Payslip::create($data);
                    }

                    $account_balance = DB::table('finance_bank_cashes')->where('id', config('variable.account_id'))->pluck('account_balance')->first();

                    if ((int) $account_balance < $total_sum) {
                        throw new Exception("requested balance is less then available balance");
                    }

                    $new_balance = (int) $account_balance - (int) $total_sum;

                    $finance_data = [];

                    $finance_data['account_id'] = config('variable.account_id');
                    $finance_data['amount'] = $total_sum;
                    $finance_data['expense_date'] = now()->format(config('app.Date_Format'));
                    $finance_data['expense_reference'] = trans('file.Payroll');

                    FinanceBankCash::whereId($finance_data['account_id'])->update(['account_balance' => $new_balance]);

                    $Expense = FinanceTransaction::create($finance_data);

                    $finance_data['id'] = $Expense->id;

                    FinanceExpense::create($finance_data);

                    DB::commit();
                } catch (Exception $e) {
                    DB::rollback();
                    return response()->json(['error' => $e->getMessage()]);
                } catch (Throwable $e) {
                    DB::rollback();
                    return response()->json(['error' => $e->getMessage()]);
                }

                return response()->json(['success' => __('Paid Successfully')]);
            }
        }

        return response()->json(['error' => __('Error')]);
    }

    protected function allowances($employee, $first_date, $type)
    {
        if ($type == "getArray") {
            if (!$employee->allowances->isEmpty()) {
                foreach ($employee->allowances as $item) {
                    if ($item->first_date <= $first_date) {
                        $allowances = array();
                        foreach ($employee->allowances as $key => $value) {
                            if ($value->first_date <= $first_date) {
                                //$allowances = array();
                                if ($item->first_date == $value->first_date) {
                                    $allowances[] = $employee->allowances[$key];
                                }
                            }
                        }

                    }
                }
            } else {
                $allowances = [];
            }
            return $allowances;
        } elseif ($type == "getAmount") {
            $allowance_amount = 0;
            if (!$employee->allowances->isEmpty()) {
                foreach ($employee->allowances as $item) {
                    if ($item->first_date <= $first_date) {
                        // $allowance_amount = SalaryAllowance::where('month_year',$item->month_year)->where('employee_id',$item->employee_id)->sum('allowance_amount');
                        $allowance_amount = 0;
                        foreach ($employee->allowances as $value) {
                            if ($value->first_date <= $first_date) {
                                if ($item->first_date == $value->first_date) {
                                    $allowance_amount += $value->allowance_amount;
                                }
                            }
                        }
                    }
                }
            }

            return $allowance_amount;
        }

    }

    protected function deductions($employee, $first_date, $type)
    {
        if ($type == "getAmount") {
            $deduction_amount = 0;
            if (!$employee->deductions->isEmpty()) {
                foreach ($employee->deductions as $item) {
                    if ($item->first_date <= $first_date) {
                        $deduction_amount = 0;
                        foreach ($employee->deductions as $value) {
                            if ($value->first_date <= $first_date) {
                                if ($item->first_date == $value->first_date) {
                                    $deduction_amount += $value->deduction_amount;
                                }
                            }
                        }
                    }
                }
            }
            return $deduction_amount;
        } elseif ($type == "getArray") {
            if (!$employee->deductions->isEmpty()) {
                foreach ($employee->deductions as $item) {
                    if ($item->first_date <= $first_date) {
                        $deductions = array();
                        foreach ($employee->deductions as $key => $value) {
                            if ($value->first_date <= $first_date) {
                                if ($item->first_date == $value->first_date) {
                                    $deductions[] = $employee->deductions[$key];
                                }
                            }
                        }
                    }
                }
            } else {
                $deductions = [];
            }
            return $deductions;
        }
    }
}