<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;
use Illuminate\Support\Facades\Auth;

use App\Imports\AttendancesImport;
use App\Http\traits\MonthlyWorkedHours;

use Carbon\Carbon;

use App\leave;
use App\office_shift;
use App\Attendance;
use App\company;
use App\Employee;
use App\Holiday;
use App\LatePolicy;

use DateInterval;
use DatePeriod;
use DateTime;
use Exception;

class AttendanceController extends Controller {

    use MonthlyWorkedHours;

    public $date_attendance = [];
    public $date_range = [];
    public $work_days = 0;

    public function index(Request $request)
    {
        $logged_user = auth()->user();
        //checking if date is selected else date is current
        // if ($logged_user->can('view-attendance'))
        // {
            $selected_date = Carbon::parse($request->filter_month_year)->format('Y-m-d') ?? now()->format('Y-m-d');

            $day = strtolower(Carbon::parse($request->filter_month_year)->format('l')) . '_in' ?? strtolower(now()->format('l')) . '_in';


            if (request()->ajax())
            {
                //employee attendance of selected date

                // if($logged_user->role_users_id != 1){
                if(!($logged_user->can('view-attendance'))){ //Correction
                    $employee = Employee::with(['officeShift', 'employeeAttendance' => function ($query) use ($selected_date)
                    {
                        $query->where('attendance_date', $selected_date);
                    },
                        'officeShift',
                        'company:id,company_name',
                        'employeeLeave' => function ($query) use ($selected_date)
                        {
                            $query->where('start_date', '<=', $selected_date)
                                ->where('end_date', '>=', $selected_date);
                        }]
                    )
                    ->select('id', 'company_id', 'first_name', 'last_name', 'office_shift_id')
                    ->where('id', '=', $logged_user->id)
                    ->where('is_active',1)
                    ->where('exit_date',NULL)
                    ->get();

                }
                else{
                    $employee = Employee::with(['officeShift', 'employeeAttendance' => function ($query) use ($selected_date)
                    {
                        $query->where('attendance_date', $selected_date);
                    },
                        'officeShift',
                        'company:id,company_name',
                        'employeeLeave' => function ($query) use ($selected_date)
                        {
                            $query->where('start_date', '<=', $selected_date)
                                ->where('end_date', '>=', $selected_date);
                        }]
                    )
                    ->select('id', 'company_id', 'first_name', 'last_name', 'office_shift_id')
                    ->where('is_active',1)
                    ->where('exit_date',NULL)
                    ->get();
                }

                $holidays = Holiday::select('id', 'company_id', 'start_date', 'end_date', 'is_publish')
                    ->where('start_date', '<=', $selected_date)
                    ->where('end_date', '>=', $selected_date)
                    ->where('is_publish', '=', 1)->first();


                return datatables()->of($employee)
                    ->setRowId(function ($employee)
                    {
                        return $employee->id;
                    })
                    ->addColumn('employee_name', function ($employee)
                    {
                        return $employee->full_name;
                    })
                    ->addColumn('company', function ($employee)
                    {
                        return $employee->company->company_name;
                    })
                    ->addColumn('attendance_date', function ($employee) use ($selected_date)
                    {
                        //if there is no employee attendance
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return Carbon::parse($selected_date)->format(config('app.Date_Format'));
                        } else
                        {
                            //if there are employee attendance,get the first record
                            $attendance_row = $employee->employeeAttendance->first();

                            return $attendance_row->attendance_date;
                        }
                    })
                    ->addColumn('attendance_status', function ($employee) use ($holidays, $day)
                    {
                        //if there are employee attendance,get the first record
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            if (is_null($employee->officeShift->$day ?? null) || ($employee->officeShift->$day == ''))
                            {
                                return __('Off Day');
                            }

                            if ($holidays)
                            {
                                if ($employee->company_id == $holidays->company_id)
                                {
                                    return trans('file.Holiday');
                                }
                            }


                            if ($employee->employeeLeave->isEmpty())
                            {
                                return trans('file.Absent');
                            }

                            return __('On leave');

                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->first();

                            return $attendance_row->attendance_status;
                        }
                    })                  
                    ->addColumn('clock_in', function ($employee)
                    {
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return '---';
                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->first();

                            return $attendance_row->clock_in;
                        }
                    })
                    ->addColumn('clock_out', function ($employee)
                    {
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return '---';
                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->last();

                            return $attendance_row->clock_out;
                        }
                    })
                    ->addColumn('time_late', function ($employee)
                    {
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return '---';
                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->first();

                            return $attendance_row->time_late;
                        }
                    })
                    ->addColumn('early_leaving', function ($employee)
                    {
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return '---';
                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->last();

                            return $attendance_row->early_leaving;
                        }
                    })                  
                    ->addColumn('overtime', function ($employee)
                    {
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return '---';
                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->last();
                            return $attendance_row->overtime;
                        }
                    })
                    ->addColumn('total_work', function ($employee)
                    {
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return '---';
                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->last();
                            return $attendance_row->total_work;
                        }
                    })
                    ->addColumn('total_rest', function ($employee)
                    {
                        if ($employee->employeeAttendance->isEmpty())
                        {
                            return '---';
                        } else
                        {
                            $attendance_row = $employee->employeeAttendance->last();
                            return $attendance_row->total_rest;
                        }
                    })
                    ->rawColumns(['clock_in', 'clock_out'])
                    ->make(true);
            }

            return view('timesheet.attendance.attendance');
        // }

        return response()->json(['success' => __('You are not authorized')]);
    }


    public function employeeAttendance(Request $request, $id)
    {
        //********** Test Start ************

        // $temp_current_time = new DateTime('09:00');
        // $shift_in = new DateTime($request->office_shift_in);
        // $data['clock_in'] = $temp_current_time->format('H:i');
        // $timeDifference = $shift_in->diff(new DateTime($data['clock_in']))->format('%H:%I');
        // $data['time_late'] = $timeDifference;
        // $late_count = $data['time_late'];

        // //End
        // $current_time = new DateTime(now());
        // $shift_out = new DateTime($request->office_shift_out);
        // $data['clock_out'] = $current_time->format('H:i');
        // $timeDifference = $shift_out->diff(new DateTime($data['clock_out']))->format('%H:%I');
        // $data['overtime'] = $timeDifference;
        // $leave_time = new DateTime($data['overtime']);

        // $originalOvertime =   $leave_time->diff(new DateTime($late_count));  //$data['clock_out'];
        // return $originalOvertime;


        //********** Test End ************


        $data = [];

        //current day
        $current_day = now()->format(config('app.Date_Format'));

        //getting the latest instance of employee_attendance
        $employee_attendance_last = Attendance::where('attendance_date', now()->format('Y-m-d'))
                ->where('employee_id', $id)->orderBy('id', 'desc')->first() ?? null;



        //shift in-shift out timing
        try
        {
            $shift_in = new DateTime($request->office_shift_in);
            $shift_out = new DateTime($request->office_shift_out);
            $current_time = new DateTime(now());

        } catch (Exception $e)
        {
            return $e;
        }

        $data['employee_id'] = $id;
        $data['attendance_date'] = $current_day;

        //if employee attendance record was not found
        // FOR CLOCK IN
        if (!$employee_attendance_last)
        {
            // if employee is late
            if ($current_time > $shift_in)
            {
                $data['clock_in'] = $current_time->format('H:i');
                $timeDifference = $shift_in->diff(new DateTime($data['clock_in']))->format('%H:%I');
                $data['time_late'] = $timeDifference;

                // return $data['time_late']->format('H:i');

            } // if employee is early or on time
            else
            {
                // if(early clockin shifter ketre jadi enable take) {
                //     $data['clock_in'] = $current_time->format('H:i');
                //     $timeDifference = $shift_in->diff(new DateTime($data['clock_in']))->format('%H:%I');
                //     $data['overtime'] = $timeDifference; // এটা পরবর্তী overtime এর সাথে যোগ করতে হবে ।
                // }
                // else {
                    $data['clock_in'] = $shift_in->format('H:i');
                //}
            }

            $data['attendance_status'] = 'present';
            $data['clock_in_out'] = 1;
            $data['clock_in_ip'] = $request->ip();

            //creating new attendance record

            Attendance::create($data);

            $this->setSuccessMessage(__('Clocked In Successfully'));

            return redirect()->back();
        }

        // if there is a record of employee attendance
        //FOR CLOCK OUT
        //if ($employee_attendance_last)
        else
        {
            //checking if the employee is not both clocked in + out (1)
            if ($employee_attendance_last->clock_in_out == 1)
            {
                $employee_last_clock_in = new DateTime($employee_attendance_last->clock_in);
                // if employee is early leaving
                if ($current_time < $shift_out)
                {
                    $data['clock_out'] = $current_time->format('H:i');
                    $timeDifference = $shift_out->diff(new DateTime($data['clock_out']))->format('%H:%I');
                    $data['early_leaving'] = $timeDifference;
                } // if employee is doing overtime
                elseif ($current_time > $shift_out)
                {
                    $data['clock_out'] = $current_time->format('H:i');
                    if ($employee_last_clock_in > $shift_out)
                    {
                        $timeDifference = $employee_last_clock_in->diff(new DateTime($data['clock_out']))->format('%H:%I');
                    }
                    else
                    {
                        $timeDifference = $shift_out->diff(new DateTime($data['clock_out']))->format('%H:%I');
                    }
                    // $data['overtime'] = $timeDifference; //Previous

                    //**************************** CUSTOMIZATION START ****************************
                    $extraWork = new DateTime($timeDifference);
                    $timeLate = new DateTime($employee_attendance_last->time_late);

                    if ($extraWork >= $timeLate) {
                        $data['overtime'] = $extraWork->diff($timeLate)->format('%H:%I');
                    }else {
                        $data['overtime'] = $timeDifference;
                    }

                    // **************************** CUSTOMIZATION END ****************************

                    // $employee_attendance_last
                    //Working Here

                } //if clocked out in time
                else
                {
                    $data['clock_out'] = $shift_out->format('H:i');
                }

                // return $data['overtime'];

                $data['clock_out_ip'] = $request->ip();

                // calculating total work
                $total_work = $employee_last_clock_in->diff(new DateTime($data['clock_out']))->format('%H:%I');
                $data['total_work'] = $total_work;
                $data['clock_in_out'] = 0;


                //updating record
                $attendance = Attendance::findOrFail($employee_attendance_last->id);
                $attendance->update($data);
                $this->setSuccessMessage(__('Clocked Out Successfully'));

                return redirect()->back();
            }
            // if employee is both clocked in + out
            // if ($employee_attendance_last->clock_in_out == 0)
            else
            {
                // new clock in on that day
                $data['clock_in'] = $current_time->format('H:i');
                $data['clock_in_ip'] = $request->ip();
                $data['clock_in_out'] = 1;
                // last clock out (needed for calculation rest time)
                $employee_last_clock_out = new DateTime($employee_attendance_last->clock_out);
                // try
                // {

                // } catch (Exception $e)
                // {
                //  return $e;
                // }
                // calculating total rest (last clock out ~ current clock in)
                $data['total_rest'] = $employee_last_clock_out->diff(new DateTime($data['clock_in']))->format('%H:%I');
                // creating new attendance
                Attendance::create($data);

                $this->setSuccessMessage(__('Clocked In Successfully'));

                return redirect()->back();
            }
        }

        return response()->json(trans('file.Success'));
    }


    public function dateWiseAttendance(Request $request)
    {

        $logged_user = auth()->user();

        // if ($logged_user->can('view-attendance'))
        // {
            $companies = Company::all('id', 'company_name');

            $reqData = ['company_id' => $request->company_id, 'department_id' => $request->department_id, 'employee_id' => $request->employee_id];

            //$request->department_id = 3;
            //$request->filter_start_date = '15-Dec-2021';
            //$request->filter_end_date = '16-Dec-2021';

            $start_date = Carbon::parse($request->filter_start_date)->format('Y-m-d') ?? '';
            $end_date = Carbon::parse($request->filter_end_date)->format('Y-m-d') ?? '';

            if (request()->ajax())
            {
                if (!$request->company_id && !$request->department_id && !$request->employee_id)
                {
                    $attendance = [];
                }
                else
                {
                    $attendance = Attendance::with([
                        'officeShift',
                        'employee' => function ($query) use ($reqData)
                        {
                            $query->with(['officeShift','employeeLeave','company.companyHolidays'])
                            ->where('is_active', '=', 1);
                        }
                    ])
                    ->whereHas('employee', function (Builder $query)use ($reqData) {
                        $query->when($reqData['department_id'], function ($subquery, $departmentId) {
                            $subquery->where('department_id', $departmentId);
                        })
                        ->when($reqData['company_id'], function ($subquery, $companyId) {
                            $subquery->where('company_id', $companyId);
                        })
                        ->when($reqData['employee_id'], function ($query, $employeeId) {
                            $query->where('id', $employeeId);
                        });
                    })
                    ->whereBetween('attendance_date', [$start_date, $end_date])
                    ->orderBy('attendance_date')->get()->toArray();
                }

                return datatables()->of($attendance)
                    ->setRowId(function ($row)
                    {
                        return $row['id'];
                    })
                    ->addColumn('employee_name', function ($row)
                    {
                        return $row['employee']['first_name']." ".$row['employee']['last_name'];
                    })
                    ->addColumn('company', function ($row)
                    {
                        return $row['employee']['company']['company_name'];
                    })
                    ->addColumn('attendance_date', function ($row)
                    {
                        return $row['attendance_date'];
                    })
                    ->addColumn('attendance_status', function ($row)
                    {
                        return $row['attendance_status'];
                    })
                    ->addColumn('shift', function ($row)
                    {
                        $office_shift = empty($row['office_shift']) ? $row['employee']['office_shift'] : $row['office_shift'];
                        $officeShiftSelect = '<select class="form-control" id="office_shift_'.$row['id'].'">';
                        foreach (office_shift::all() as $officeShiftKey => $officeShiftValue) {
                            $officeShiftSelect .= '<option '.(($office_shift['id'] == $officeShiftValue['id']) ? 'Selected' : null) .' value="'.$officeShiftValue['id'].'" >'.$officeShiftValue['shift_name'].'</option>';
                        }
                        $officeShiftSelect .='</select>';

                        return $officeShiftSelect;
                    })
                    ->addColumn('is_over_time', function ($row)
                    {
                        return '<input type="checkbox" name="is_over_time" id="is_over_time_'.$row['id'].'" '.(($row['is_over_time'] == 1) ? 'checked' : null) .' />';
                    })
                    ->addColumn('clock_in', function ($row)
                    {
                        return $row['clock_in'];
                    })
                    ->addColumn('clock_out', function ($row)
                    {
                        return $row['clock_out'];
                    })
                    ->addColumn('time_late', function ($row)
                    {
                        return $row['time_late'];
                    })
                    ->addColumn('early_leaving', function ($row)
                    {
                        return $row['early_leaving'];
                    })
                    ->addColumn('overtime', function ($row)
                    {
                        return $row['overtime'];
                    })
                    ->addColumn('total_work', function ($row)
                    {
                        return $row['total_work'];
                    })
                    ->addColumn('total_rest', function ($row)
                    {
                        return $row['total_rest'];
                    })
                    ->addColumn('submit', function ($row)
                    {
                        return '<button row_id="'.$row['id'].'" class="save_btn btn btn-primary">Save</button>';
                    })
                    ->rawColumns(['shift','is_over_time','submit'])
                    ->make(true);
            }

            return view('timesheet.dateWiseAttendance.index', compact('companies'));
        
        // }

        // return response()->json(['success' => __('You are not authorized')]);

    }


    public function monthlyAttendance(Request $request)
    {
        $logged_user = auth()->user();
        $companies = Company::all('id', 'company_name');


        $month_year = $request->filter_month_year;


        $first_date = date('Y-m-d', strtotime('first day of ' . $month_year));
        $last_date = date('Y-m-d', strtotime('last day of ' . $month_year));

        $begin = new DateTime($first_date);
        $end = new DateTime($last_date);

        $end->modify('+1 day');

        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod($begin, $interval, $end);


        foreach ($period as $dt)
        {
            $this->date_range[] = $dt->format("d D");
            $this->date_attendance[] = $dt->format(config('app.Date_Format'));
        }


        // if ($logged_user->can('view-attendance'))
        // {
            if (request()->ajax())
            {
                if(!($logged_user->can('view-attendance'))) //Correction
                {
                    $employee = Employee::with(['officeShift', 'employeeAttendance' => function ($query) use ($first_date, $last_date)
                    {
                        $query->whereBetween('attendance_date', [$first_date, $last_date]);
                    },
                        'employeeLeave',
                        'company:id,company_name',
                        'company.companyHolidays'
                    ])
                    ->select('id', 'company_id', 'first_name', 'last_name', 'office_shift_id')
                    ->where('is_active',1)
                    ->where('exit_date',NULL)
                    ->whereId($logged_user->id)
                    ->get();
                }
                else
                {
                    //Previous
                    if (!empty($request->filter_company && $request->filter_employee))
                    {

                        $employee = Employee::with(['officeShift', 'employeeAttendance' => function ($query) use ($first_date, $last_date)
                        {
                            $query->whereBetween('attendance_date', [$first_date, $last_date]);
                        },
                            'employeeLeave',
                            'company:id,company_name',
                            'company.companyHolidays'
                        ])
                            ->select('id', 'company_id', 'first_name', 'last_name', 'office_shift_id')
                            ->whereId($request->filter_employee)->get();

                    } elseif (!empty($request->filter_company))
                    {
                        $employee = Employee::with(['officeShift', 'employeeAttendance' => function ($query) use ($first_date, $last_date)
                        {
                            $query->whereBetween('attendance_date', [$first_date, $last_date]);
                        },
                            'employeeLeave',
                            'company:id,company_name',
                            'company.companyHolidays'
                        ])
                            ->select('id', 'company_id', 'first_name', 'last_name', 'office_shift_id')
                            ->where('company_id', $request->filter_company)->where('is_active',1)
                            ->where('exit_date',NULL)->get();
                    }
                    else
                    {
                        $employee = Employee::with(['officeShift', 'employeeAttendance' => function ($query) use ($first_date, $last_date)
                        {
                            $query->whereBetween('attendance_date', [$first_date, $last_date]);
                        },
                            'employeeLeave',
                            'company:id,company_name',
                            'company.companyHolidays'
                        ])
                            ->select('id', 'company_id', 'first_name', 'last_name', 'office_shift_id')
                            ->where('is_active',1)
                            ->where('exit_date',NULL)
                            ->get();
                    }
                }

                return datatables()->of($employee)
                    ->setRowId(function ($row)
                    {
                        $this->work_days = 0;

                        return $row->id;
                    })
                    ->addColumn('employee_name', function ($row)
                    {
                        $name = $row->full_name;
                        $company_name = $row->company->company_name;

                        return $name . '(' . $company_name . ')';

                    })
                    ->addColumn('day1', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 0);
                    })
                    ->addColumn('day2', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 1);
                    })
                    ->addColumn('day3', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 2);
                    })
                    ->addColumn('day4', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 3);
                    })
                    ->addColumn('day5', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 4);
                    })
                    ->addColumn('day6', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 5);
                    })
                    ->addColumn('day7', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 6);
                    })
                    ->addColumn('day8', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 7);
                    })
                    ->addColumn('day9', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 8);
                    })
                    ->addColumn('day10', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 9);
                    })
                    ->addColumn('day11', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 10);
                    })
                    ->addColumn('day12', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 11);
                    })
                    ->addColumn('day13', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 12);
                    })
                    ->addColumn('day14', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 13);
                    })
                    ->addColumn('day15', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 14);
                    })
                    ->addColumn('day16', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 15);
                    })
                    ->addColumn('day17', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 16);
                    })
                    ->addColumn('day18', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 17);
                    })
                    ->addColumn('day19', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 18);
                    })
                    ->addColumn('day20', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 19);
                    })
                    ->addColumn('day21', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 20);
                    })
                    ->addColumn('day22', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 21);
                    })
                    ->addColumn('day23', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 22);
                    })
                    ->addColumn('day24', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 23);
                    })
                    ->addColumn('day25', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 24);
                    })
                    ->addColumn('day26', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 25);
                    })
                    ->addColumn('day27', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 26);
                    })
                    ->addColumn('day28', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 27);
                    })
                    ->addColumn('day29', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 28);
                    })
                    ->addColumn('day30', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 29);
                    })
                    ->addColumn('day31', function ($row)
                    {
                        return $this->checkAttendanceStatus($row, 30);
                    })
                    ->addColumn('worked_days', function ($row)
                    {
                        return $this->work_days;
                    })
                    ->addColumn('total_worked_hours', function ($row)
                    {
                        return $this->totalWorkedHours($row);
                    })
                    // ->addColumn('total_worked_hours', function ($row) use ($month_year)
                    // {
                    //  if ($month_year) {
                    //      return $this->MonthlyTotalWorked($month_year,$row->id);
                    //  }
                    //  else{
                    //      return $this->totalWorkedHours($row);
                    //  }
                    // })
                    ->with([
                        'date_range' => $this->date_range,
                    ])
                    ->make(true);
            }

            return view('timesheet.monthlyAttendance.index', compact('companies'));
        // }
        // return response()->json(['success' => __('You are not authorized')]);
    }


    public function checkAttendanceStatus($emp, $index)
    {

        if (count($this->date_attendance) <= $index)
        {
            return '';
        } else
        {
            $present = $emp->employeeAttendance->where('attendance_date', $this->date_attendance[$index]);

            $leave = $emp->employeeLeave->where('start_date', '<=', $this->date_attendance[$index])
                ->where('end_date', '>=', $this->date_attendance[$index]);

            $holiday = $emp->company->companyHolidays->where('start_date', '<=', $this->date_attendance[$index])
                ->where('end_date', '>=', $this->date_attendance[$index]);

            $day = strtolower(Carbon::parse($this->date_attendance[$index])->format('l')) . '_in';

            if ($present->isNotEmpty())
            {
                $this->work_days++;

                return 'P';
            } elseif (!$emp->officeShift->$day)
            {
                return 'O';
            } elseif ($leave->isNotEmpty())
            {
                return 'L';
            } elseif ($holiday->isNotEmpty())
            {
                return 'H';
            } else
            {
                return 'A';
            }
        }
    }

    public function updateAttendance(Request $request)
    {

        $logged_user = auth()->user();
        $companies = company::select('id', 'company_name')->get();
        if ($logged_user->can('edit-attendance'))
        {
            if (request()->ajax())
            {

                $employee_attendance = Attendance::where('employee_id', $request->employee_id)
                    ->where('attendance_date', Carbon::parse($request->attendance_date)->format('Y-m-d'))->get();


                return datatables()->of($employee_attendance)
                    ->setRowId(function ($row)
                    {
                        return $row->id;
                    })
                    ->addColumn('clock_in', function ($row)
                    {
                        return $row->clock_in;
                    })
                    ->addColumn('clock_out', function ($row)
                    {
                        return $row->clock_out;
                    })
                    ->addColumn('total_work', function ($row)
                    {
                        return $row->total_work;
                    })
                    ->addColumn('action', function ($row)
                    {
                        if (auth()->user()->can('user-edit'))
                        {
                            $button = '<button type="button" name="edit" id="' . $row->id . '" class="edit btn btn-primary btn-sm"><i class="dripicons-pencil"></i></button>';
                            $button .= '<br>&nbsp;&nbsp;';
                            $button .= '<button type="button" name="delete" id="' . $row->id . '" class="delete btn btn-danger btn-sm"><i class="dripicons-trash"></i></button>';

                            return $button;
                        } else
                        {
                            return '';
                        }
                    })
                    ->rawColumns(['action'])
                    ->make(true);
            }

            return view('timesheet.updateAttendance.index', compact('companies'));
        }
        return response()->json(['success' => __('You are not authorized')]);
    }

    public function updateAttendanceGet($id)
    {

        $attendance = Attendance::select('id', 'clock_in', 'clock_out', 'attendance_date')
            ->findOrFail($id);

        return response()->json(['data' => $attendance]);
    }

    public function updateAttendanceStore(Request $request)
    {

        $data = $this->attendanceHandler($request);

        Attendance::create($data);

        return response()->json(['success' => __('Data is successfully updated')]);
    }

    public function attendanceHandler($request)
    {
        $validator = Validator::make($request->only('attendance_date', 'clock_in', 'clock_out'),
            [
                'attendance_date' => 'required|date',
                'clock_in' => 'required',
                'clock_out' => 'required'
            ]);


        if ($validator->fails())
        {
            return response()->json(['errors' => $validator->errors()->all()]);
        }

        $employee_id = $request->employee_id;
        $attendance_date = $request->attendance_date;
        $clock_in = $request->clock_in;
        $clock_out = $request->clock_out;


        try
        {
            $clock_in = new DateTime($clock_in);
            $clock_out = new DateTime($clock_out);
        } catch (Exception $e)
        {
            return $e;
        }

        $attendance_date_day = Carbon::parse($request->attendance_date)->format('l');


        $employee = Employee::with('officeShift')->findOrFail($employee_id);

        $current_day_in = strtolower($attendance_date_day) . '_in';
        $current_day_out = strtolower($attendance_date_day) . '_out';


        $shift_in = $employee->officeShift->$current_day_in;
        $shift_out = $employee->officeShift->$current_day_out;


        if ($shift_in == null)
        {

            $data['employee_id'] = $employee_id;
            $data['attendance_date'] = $attendance_date;
            $data['clock_in'] = $clock_in->format('H:i');
            $data['clock_out'] = $clock_out->format('H:i');
            $data['attendance_status'] = 'present';


            $total_work = $clock_in->diff($clock_out)->format('%H:%I');
            $data['total_work'] = $total_work;
            $data['early_leaving'] = '00:00';
            $data['time_late'] = '00:00';
            $data['overtime'] = '00:00';
            $data['clock_in_out'] = 0;

            return $data;
        }


        //shift in-shift out timing
        try
        {
            $shift_in = new DateTime($shift_in);
            $shift_out = new DateTime($shift_out);


        } catch (Exception $e)
        {
            return $e;
        }

        $data['employee_id'] = $employee_id;
        $data['attendance_date'] = $attendance_date;


        // if employee is late
        if ($clock_in > $shift_in)
        {
            $timeDifference = $shift_in->diff($clock_in)->format('%H:%I');
            $data['clock_in'] = $clock_in->format('H:i');
            $data['time_late'] = $timeDifference;
        } // if employee is early or on time
        else
        {
            $data['clock_in'] = $shift_in->format('H:i');
            $data['time_late'] = '00:00';
        }
        if ($clock_out < $shift_out)
        {

            $timeDifference = $shift_out->diff($clock_out)->format('%H:%I');
            $data['clock_out'] = $clock_out->format('H:i');
            $data['early_leaving'] = $timeDifference;
        } // if employee is doing overtime
        elseif
        ($clock_out > $shift_out)
        {
            $timeDifference = $shift_out->diff($clock_out)->format('%H:%I');
            $data['clock_out'] = $clock_out->format('H:i');
            $data['overtime'] = $timeDifference;
            $data['early_leaving'] = '00:00';
        } //if clocked out in time
        else
        {
            $data['clock_out'] = $shift_out->format('H:i');
            $data['overtime'] = '00:00';
            $data['early_leaving'] = '00:00';
        }
        $data['attendance_status'] = 'present';


        $total_work = $clock_in->diff($clock_out)->format('%H:%I');
        $data['total_work'] = $total_work;
        $data['clock_in_out'] = 0;

        return $data;

    }

    public function updateAttendanceUpdate(Request $request)
    {

        $data = $this->attendanceHandler($request);

        $id = $request->hidden_id;
        //creating new attendance record
        Attendance::find($id)->update($data);

        return response()->json(['success' => __('Data is successfully updated')]);

    }

    public function updateAttendanceDelete($id)
    {
        $logged_user = auth()->user();

        if ($logged_user->can('delete-attendance'))
        {
            Attendance::whereId($id)->delete();

            return response()->json(['success' => __('Data is successfully deleted')]);
        }

        return response()->json(['error' => __('You are not authorized')]);
    }


    public function import()
    {
        $logged_user = auth()->user();
        if ($logged_user->can('delete-attendance'))
        {
            $attendance_data = [];
            if (file_exists(public_path('uploads/csv/').'attendance.csv')) {
                $device_attendance = array_map('str_getcsv', file(public_path('uploads/csv/').'attendance.csv'));
                array_shift($device_attendance);
                
               try {
                    foreach($device_attendance as $key => $dev_att) {
                        $date_dev_att=date_create_from_format("d/m/Y g:i a", $dev_att[3]);
                        $attendance_data[$key] = ['card_no' => $dev_att[2], 'date'=>date_format($date_dev_att,"Y-m-d"), 'time'=>date_format($date_dev_att,"H:i")];
                    }
                } catch (\Throwable $e) {
                 
                    dd($e);
                }

                unlink(public_path('uploads/csv/').'attendance.csv');
            }
            if (!empty($attendance_data)) {

                // dd($attendance_data);

                $employee_data =  Employee::with('officeShift','company.late')->select('id', 'card_no', 'company_id', 'department_id', 'office_shift_id')->get()->keyBy('card_no')->toArray();
                $holidays = Holiday::select('event_name', 'company_id', 'start_date','end_date')->get()->groupBy('company_id')->toArray();
                $leaves = leave::select('employee_id', 'leave_reason','start_date','end_date')->where('status','approved')->get()->groupBy('employee_id')->toArray();

                $employee_present = [];
                $present_attendance = [];
                
                $attendance_data = collect($attendance_data)->groupBy('date')->toArray();
                
                foreach ($attendance_data as $key => $value) {
                    $attendance_data[$key] = collect($value)->groupBy('card_no')->toArray();
                    foreach ($attendance_data[$key] as $key1 => $value1) {
                        $columns = array_column($value1, 'time');
                        array_multisort($columns, SORT_ASC, $value1);
                        $employee_present[$key][] = ['employee'=> $employee_data[$value1[0]['card_no']], 'time'=> array_column($value1, 'time')];
                    }
                }

                foreach ($employee_present as $att_date => $emp_pres_att_dt) {
                    
                    foreach ($emp_pres_att_dt as $epa) {

                        // dd($epa);

                        $lateTime = $epa['employee']['company']['late'];

                        $is_att_date_closed = 0;

                        if ($epa['employee']['office_shift']['shift_name'] == 'NIGHT SHIFT') {

                            $a_date = strtotime($att_date);
                            $before_att_date = date('Y-m-d', strtotime('-1 day', $a_date));

                            $clock_out = new DateTime($epa['time'][0]);
                            
                            $current_clock_in = new DateTime(end($epa['time']));

                            $day_name = strtolower(date('l', $a_date));

                            $before_attendance = Attendance::select('employee_id', 'attendance_date', 'clock_in', 'clock_out')->where('attendance_date', '=', $before_att_date)->where('employee_id', '=', $epa['employee']['id'])->first()->toArray();

                            $clock_in = new DateTime($before_attendance['clock_in']);

                            // dd($current_clock_in->format('H:i'));

                            if(!empty($epa['time']) && $before_attendance){

                                $shift_in = $epa['employee']['office_shift'][$day_name.'_in'] == "OFF" ? new DateTime("08:00 PM") : new DateTime($epa['employee']['office_shift'][$day_name.'_in']);
                                $shift_out = $epa['employee']['office_shift'][$day_name.'_in'] == "OFF" ? new DateTime("05:00 AM") : new DateTime($epa['employee']['office_shift'][$day_name.'_out']);

                                if ($clock_in > $shift_in && !empty($lateTime)) {

                                    $time_late = $shift_in->diff($clock_in)->format('%H:%I');
                                    sscanf($time_late, '%d:%d', $hour, $min);
                                    
                                    $att_time_late = $hour * 60 + $min;
                                    
                                    if ($att_time_late > $lateTime['second_late']) {
                                        $time_late = $lateTime['second_deduction'];
                                    }
                                    elseif ($att_time_late > $lateTime['first_late']) {
                                        $time_late = $lateTime['first_deduction'];
                                    }
                                    elseif ($att_time_late > 0) {
                                        $time_late = '00:00';
                                    }
                                } // if employee is early or on time
                                else {
                                    $time_late = '00:00';
                                }

                                if ($clock_in == $clock_out) {

                                    $epa_time_fisrt_index = $epa['time'][0];
                                    $epa['time'] = [];
                                    $epa['time'][] = $epa_time_fisrt_index;
                                    array_push($epa['time'],'---');
                                    $early_leaving = '00:00';
                                    $overtime = "00:00";
                                    $total_work = "00:00";

                                }else {

                                    $total_work = $clock_in->diff($clock_out)->format('%H:%I');

                                    dd($total_work,$    ,$clock_out);
                                    sscanf($total_work, '%d:%d', $hour, $min);
                                    
                                    $total_work_min = $hour * 60 + $min;
                                    sscanf($time_late, '%d:%d', $hour, $min);
                                    $att_time_late = $hour * 60 + $min;
                                    
                                    $total_work_min = $total_work_min - $att_time_late;

                                    if ($h = floor($total_work_min / 60)) {
                                            $total_work_min %= 60;
                                    }
                                    $total_work = sprintf('%02d:%02d', $h, $total_work_min);

                                    // if employee is early leaving
                                    if ($clock_out < $shift_out) {
                                        $early_leaving = $shift_out->diff($clock_out)->format('%H:%I');
                                        $overtime = '00:00';
                                    } // if employee is doing overtime
                                    elseif ($clock_out > $shift_out) {
                                        $early_leaving = '00:00';
                                        $total_work_dt = new DateTime($total_work);
                                        $shift_time_dt = new DateTime($shift_in->diff($shift_out)->format('%H:%I'));
                                        if ($total_work_dt > $shift_time_dt) {
                                            $overtime = $total_work_dt->diff($shift_time_dt)->format('%H:%I');
                                        } else {
                                            $overtime = '00:00';
                                        }
                                    } //if clocked out in time
                                    else {
                                        $early_leaving = '00:00';
                                        $overtime = '00:00';
                                    }
                                }

                                $present_attendance = ['employee_id' => $epa['employee']['id'], 'attendance_date' => $before_att_date, 'clock_in' => $clock_in->format('H:i'), 'clock_out' => $clock_out->format('H:i'), 'time_late' => $time_late, 'early_leaving' => $early_leaving, 'overtime' => $overtime, 'total_work' => $total_work];

                                if (array_key_exists($epa['employee']['company_id'], $holidays)) {
                                    foreach ($holidays[$epa['employee']['company_id']] as $hday_com) {
                                        $hs_date = strtotime($hday_com['start_date']);
                                        $he_date = strtotime($hday_com['end_date']);
                                        if ($a_date >= $hs_date && $a_date <= $he_date){
                                            $present_attendance['overtime'] = $total_work;
                                        }
                                    }
                                }

                                if($epa['employee']['office_shift'][$day_name.'_in'] == "OFF"){
                                    $present_attendance['time_late'] = "00:00";
                                    $present_attendance['early_leaving'] = "00:00";
                                    $present_attendance['overtime'] = $total_work;
                                }

                                // Attendance::where([
                                //     'employee_id'=> $epa['employee']['id'],
                                //     'attendance_date'=> $before_att_date
                                // ])->update($present_attendance);


                            }// Before Date Attendance End

                            if ($is_att_date_closed == 0 && array_key_exists($epa['employee']['id'], $leaves)) {
                                foreach ($leaves[$epa['employee']['id']] as $leave_emp) {
                                    $ls_date = strtotime($leave_emp['start_date']);
                                    $le_date = strtotime($leave_emp['end_date']);
                                    if ($a_date >= $ls_date && $a_date <= $le_date){
                                        $is_att_date_closed = 1;
                                        break;
                                    }
                                }
                            }

                            // Attendance::updateOrCreate([
                            //     'attendance_date' => $att_date,
                            //     'employee_id' => $epa['employee']['id']
                            // ],[
                            //     'clock_in' => $current_clock_in->format('H:i')
                            // ]);
                            
                        } else{ // Night Shift End

                            $clock_in = new DateTime($epa['time'][0]);
                            $clock_out = new DateTime(end($epa['time']));

                            $present_attendance = ['employee_id' => $epa['employee']['id'], 'attendance_date' => $before_att_date, 'clock_in' => $clock_in, 'time_late' => $time_late, 'early_leaving' => $early_leaving, 'overtime' => $overtime, 'total_work' => $total_work];

                            if (array_key_exists($epa['employee']['company_id'], $holidays)) {
                                foreach ($holidays[$epa['employee']['company_id']] as $hday_com) {
                                    $hs_date = strtotime($hday_com['start_date']);
                                    $he_date = strtotime($hday_com['end_date']);
                                    if ($a_date >= $hs_date && $a_date <= $he_date){
                                        $present_attendance['overtime'] = $total_work;
                                    }
                                }
                            }

                            if($epa['employee']['office_shift'][$day_name.'_in'] == "OFF"){
                                $present_attendance['time_late'] = "00:00";
                                $present_attendance['early_leaving'] = "00:00";
                                $present_attendance['overtime'] = $total_work;
                            }

                            // Attendance::insert($present_attendance);


                        } // Day Shift End

                        dd($present_attendance);

                    } // $epa end

                    dd("End ");
                } // emp_pres_att_dt End

            } // !empty attendance_data End

            return view('timesheet.attendance.import');
        } // user access End
        return abort(404,__('You are not authorized'));
    }


    public function importAttendance(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'file' => 'required|mimes:csv,txt|max:2048',
        ]);

        if($validator->fails()){
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $file = $request->file->getClientOriginalName();
        // $filename = pathinfo($file, PATHINFO_FILENAME);
        // $extension = pathinfo($file, PATHINFO_EXTENSION);
        // $file_name_extension = $filename.'.'.$extension;
        $file_name_extension = 'attendance.csv';

        $request->file->move(public_path('uploads/csv'), $file_name_extension);

        session()->flash('message','CSV Uploaded Successfully');
        return redirect()->back();
    }


    protected function MonthlyTotalWorked($month_year,$employeeId)
    {
        $year = date('Y',strtotime($month_year));
        $month = date('m',strtotime($month_year));

        $total = 0;

        $att = Employee::with(['employeeAttendance' => function ($query) use ($year,$month){
            $query->whereYear('attendance_date',$year)->whereMonth('attendance_date',$month);
        }])
        ->select('id', 'company_id', 'first_name', 'last_name', 'office_shift_id')
        ->whereId($employeeId)
        ->get();

        foreach ($att[0]->employeeAttendance as $key => $a)
        {
            sscanf($a->total_work, '%d:%d', $hour, $min);
            $total += $hour * 60 + $min;
        }

        if ($h = floor($total / 60))
        {
            $total %= 60;
        }
        $sum_total = sprintf('%02d:%02d', $h, $total);

        return $sum_total;
    }

    public function attendanceUpdate(Request $request)
    {
        $attendance = Attendance::find($request->row_id);

        if($request->is_over_time == 0){

            sscanf($attendance->total_work, '%d:%d', $tw_hour, $tw_min);
            sscanf($attendance->overtime, '%d:%d', $o_hour, $o_min);
            
            $attendance->total_work = sprintf('%02d:%02d', abs($tw_hour - $o_hour), abs($tw_min - $o_min));

            $attendance->overtime = "00:00";
        }

        $attendance->office_shift_id = $request->office_shift;
        $attendance->is_over_time = $request->is_over_time;
        $attendance->updated_by = Auth::user()->id;

        $attendance->save();

        return response()->json(['success' => __('Data is successfully updated')]);
    }
}
