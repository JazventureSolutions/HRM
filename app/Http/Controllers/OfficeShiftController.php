<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Builder;

use App\company;
use App\Employee;
use App\office_shift;

use Carbon\Carbon;

class OfficeShiftController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index(Request $request)
	{
		$logged_user = auth()->user();

		$companies = Company::all('id', 'company_name');

		$start_date = Carbon::parse($request->filter_start_date)->format('Y-m-d') ?? '';
        $end_date = Carbon::parse($request->filter_end_date)->format('Y-m-d') ?? '';

		$reqData = $request->all();

		if ($logged_user->can('view-office_shift'))
		{
			if (request()->ajax())
			{
				$officeShift = office_shift::with('company','employee')
				->when($reqData['company_id'], function ($query) use($reqData) {
                    return $query->where('company_id', $reqData['company_id']);
                })
				->whereHas('employee', function (Builder $query) use ($reqData) {
                    $query->when($reqData['department_id'], function ($subquery, $departmentId) {
                        $subquery->where('department_id', $departmentId);
                    });
                })
				->when($reqData['employee_id'], function ($query) use($reqData) {
                    return $query->where('employee_id', $reqData['employee_id']);
                })
				->whereBetween('shift_date', [$start_date, $end_date])
				->get();

				return datatables()->of($officeShift)
					->setRowId(function ($office_shift)
					{
						return $office_shift->id;
					})
					->addColumn('company', function ($row)
					{
						return $row->company->company_name ?? ' ';
					})
					->addColumn('employee', function ($row)
					{
						return $row->employee->full_name ?? ' ';
					})
					->addColumn('card_no', function ($row)
					{
						return $row->employee->card_no ?? ' ';
					})
					->addColumn('date', function ($row)
					{
						return $row->shift_date ?? ' ';
					})
					->addColumn('clock_in', function ($row)
					{
						return $row->clock_in ?? ' ';
					})
					->addColumn('clock_out', function ($row)
					{
						return $row->clock_out ?? ' ';
					})
					->addColumn('action', function ($data)
					{
						$button = '';
						if (auth()->user()->can('edit-office_shift'))
						{
							$button = '<a id="' . $data->id . '" class="edit btn btn-primary btn-sm" href="' . route('office_shift.edit', $data->id) . '"><i class="dripicons-pencil"></i></a>';
							$button .= '&nbsp;&nbsp;';
						}
						if (auth()->user()->can('delete-office_shift'))
						{
							$button .= '<button type="button" name="delete" id="' . $data->id . '" class="delete btn btn-danger btn-sm"><i class="dripicons-trash"></i></button>';
						}

						return $button;
					})
					->rawColumns(['action'])
					->make(true);
			}

			return view('timesheet.office_shift.index', compact('companies'));
		}

		return abort('403', __('You are not authorized'));
	}

	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
		$logged_user = auth()->user();
		$companies = company::select('id', 'company_name')->get();

		if ($logged_user->can('store-office_shift'))
		{
			return view('timesheet.office_shift.create', compact('companies'));
		}

		return abort('403', __('You are not authorized'));
	}

	/**
	 * Store a newly created resource in storage.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function store(Request $request)
	{
		$logged_user = auth()->user();

		if ($logged_user->can('store-office_shift'))
		{
			$validator = Validator::make($request->only('shift', 'company_id','clock_in','clock_out','start_date','end_date','employee_id'
			),
				[
					'company_id' => 'required',
					'shift' => 'required',
					'employee_id' => 'required',
					'clock_in' => 'required',
					'clock_out' => 'required',
					'start_date' => 'required',
					'end_date' => 'required'
				]
			);

			if ($validator->fails())
			{
				return response()->json(['errors' => $validator->errors()->all()]);
			}

			$data = [];

			$data['company_id'] = $request->company_id;
			$data['shift_name'] = $request->shift;
			$data['clock_in'] = $request->clock_in;
			$data['clock_out'] = $request->clock_out;
            
	        $startDate = strtotime($request->start_date);
	        $endDate = strtotime($request->end_date);

	        $employeeIdArr = in_array("all", $request->employee_id) ? Employee::where('company_id',$data['company_id'])->get()->pluck('id') : $request->employee_id;
			
	        for ($currentDate = $startDate; $currentDate <= $endDate; $currentDate += (86400)) {
				
				foreach ($employeeIdArr as $key => $value) {

					office_shift::updateOrCreate([
                        'shift_date' => date('Y-m-d', $currentDate),
                        'employee_id' => $value,
                    ], $data);
	        	}
	                                                
	        }

			return response()->json(['success' => __('Data Added successfully.')]);
		}

		return response()->json(['success' => __('You are not authorized')]);
	}


	/**
	 * Display the specified resource.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function show($id)
	{

	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function edit($id)
	{
		$logged_user = auth()->user();
			$office_shift = office_shift::with('company','employee')->where('id',$id)->first();

		if ($logged_user->can('edit-office_shift') && $office_shift)
		{
			return view('timesheet.office_shift.edit', compact('office_shift'));
		}
		return response()->json(['success' => __('You are not authorized')]);
	}


	/**
	 * Update the specified resource in storage.
	 *
	 * @param Request $request
	 * @param int $id
	 * @return Response
	 */
	public function update(Request $request)
	{
		$logged_user = auth()->user();

		if ($logged_user->can('edit-office_shift'))
		{
			$id = $request->hidden_id;

			$validator = Validator::make($request->only('shift', 'company_id','clock_in','clock_out','start_date','end_date','employee_id'),
				[
					'company_id' => 'required',
					'shift' => 'required',
					'employee_id' => 'required',
					'clock_in' => 'required',
					'clock_out' => 'required',
					'start_date' => 'required',
					'end_date' => 'required'
				]
			);

			if ($validator->fails())
			{
				return response()->json(['errors' => $validator->errors()->all()]);
			}

			$data = [];

			$data['company_id'] = $request->company_id;
			$data['shift_name'] = $request->shift;
			$data['clock_in'] = $request->clock_in;
			$data['clock_out'] = $request->clock_out;
            
	        $startDate = strtotime($request->start_date);
	        $endDate = strtotime($request->end_date);
	             
	        for ($currentDate = $startDate; $currentDate <= $endDate; $currentDate += (86400)) {

				office_shift::updateOrCreate([
                    'shift_date' => date('Y-m-d', $currentDate),
                    'employee_id' => $request->employee_id,
                ], $data);
	                                                
	        }

			return response()->json(['success' => __('Data is successfully updated')]);
		}

		return response()->json(['success' => __('You are not authorized')]);
	}

	/**
	 * Remove the specified resource from storage.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function destroy($id)
	{
		if(!config('app.USER_VERIFIED'))
		{
			return response()->json(['error' => 'This feature is disabled for demo!']);
		}
		
		$logged_user = auth()->user();

		if ($logged_user->can('delete-office_shift'))
		{
			office_shift::whereId($id)->delete();

			return response()->json(['success' => __('Data is successfully deleted')]);

		}

		return response()->json(['success' => __('You are not authorized')]);
	}

	public function delete_by_selection(Request $request)
	{
		if(!config('app.USER_VERIFIED'))
		{
			return response()->json(['error' => 'This feature is disabled for demo!']);
		}
		$logged_user = auth()->user();

		if ($logged_user->can('delete-office_shift'))
		{

			$office_shift_id = $request['officeShiftIdArray'];
			$office_shift = office_shift::whereIn('id', $office_shift_id);
			if ($office_shift->delete())
			{
				return response()->json(['success' => __('Multi Delete', ['key' => __('Office Shift')])]);
			} else
			{
				return response()->json(['error' => 'Error,selected shifts can not be deleted']);
			}
		}

		return response()->json(['success' => __('You are not authorized')]);
	}
}
