<?php

namespace App\Http\Controllers;

use App\company;
use App\Employee;
use App\Notifications\CompanyPolicyNotify;
use App\LatePolicy;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class LatePolicyController extends Controller {

	/**
	 * Display a listing of the resource.
	 *
	 * @return Response
	 */
	public function index()
	{
		$logged_user = auth()->user();
		$companies = company::select('id', 'company_name')->get();

		if (request()->ajax())
		{
			return datatables()->of(LatePolicy::with('company')->get())
			->setRowId(function ($policy)
			{
				return $policy->id;
			})
			->addColumn('company', function ($row)
			{
				return $row->company->company_name ?? ' ';
			})
			->addColumn('action', function ($data)
			{
				$button = '<button type="button" name="show" id="' . $data->id . '" class="show_new btn btn-success btn-sm"><i class="dripicons-preview"></i></button>';
				$button .= '&nbsp;&nbsp;';
				if (auth()->user()->can('edit-policy'))
				{
					$button = '<button type="button" name="edit" id="' . $data->id . '" class="edit btn btn-primary btn-sm"><i class="dripicons-pencil"></i></button>';
					$button .= '&nbsp;&nbsp;';
				}
				if (auth()->user()->can('delete-policy'))
				{
					$button .= '<button type="button" name="delete" id="' . $data->id . '" class="delete btn btn-danger btn-sm"><i class="dripicons-trash"></i></button>';
				}

				return $button;
			})
			->rawColumns(['action'])
			->make(true);
		}

		return view('organization.latepolicy.index', compact('companies'));
	}


	/**
	 * Show the form for creating a new resource.
	 *
	 * @return Response
	 */
	public function create()
	{
		//
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

		if ($logged_user->can('store-policy'))
		{
			$validator = Validator::make($request->all(),
				[
					'first_late' => 'required',
					'first_deduction' => 'required',
					'second_late' => 'required',
					'second_deduction' => 'required',
					'company_id' => 'required'
				]
			);

			if ($validator->fails())
			{
				return response()->json(['errors' => $validator->errors()->all()]);
			}

			$data = [];

			LatePolicy::updateOrCreate(
				['company_id' => $request->company_id], 
				[
					'first_late' => $request->first_late,
					'first_deduction' => $request->first_deduction,
					'second_late' => $request->second_late,
					'second_deduction' => $request->second_deduction
				]
			);

			$employee_id = Employee::where('company_id', $request['company_id'])->where('is_active',1)->where('exit_date',NULL)->pluck('id');

			$notifiable = User::whereIn('id', $employee_id)->get();

			Notification::send($notifiable, new CompanyPolicyNotify());

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
		if (request()->ajax())
		{
			$data = LatePolicy::findOrFail($id);
			$company_name = $data->company->company_name ?? '';

			return response()->json(['data' => $data, 'company_name' => $company_name]);
		}
	}

	/**
	 * Show the form for editing the specified resource.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function edit($id)
	{
		if (request()->ajax())
		{
			$data = LatePolicy::findOrFail($id);

			return response()->json(['data' => $data]);
		}
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

		if ($logged_user->can('edit-policy'))
		{
			$id = $request->hidden_id;

			$validator = Validator::make($request->all(),
				[
					'first_late' => 'required',
					'first_deduction' => 'required',
					'second_late' => 'required',
					'second_deduction' => 'required',
					'company_id' => 'required'
				]
			);

			if ($validator->fails())
			{
				return response()->json(['errors' => $validator->errors()->all()]);
			}

			$data = [];

			$data['title'] = $request->title;

			$data['company_id'] = $request->company_id;

			$data ['description'] = $request->description;
			$data ['added_by'] = $logged_user->username;


			LatePolicy::updateOrCreate(
				['company_id' => $request->company_id], 
				[
					'first_late' => $request->first_late,
					'first_deduction' => $request->first_deduction,
					'second_late' => $request->second_late,
					'second_deduction' => $request->second_deduction
				]
			);

			$employee_id = Employee::where('company_id', $data ['company_id'])->pluck('id');
			$notifiable = User::whereIn('id', $employee_id)->get();

			Notification::send($notifiable, new CompanyPolicyNotify());

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
		if (!config('app.USER_VERIFIED'))
		{
			return response()->json(['error' => 'This feature is disabled for demo!']);
		}
		$logged_user = auth()->user();

		if ($logged_user->can('delete-policy'))
		{
			LatePolicy::whereId($id)->delete();

			return response()->json(['success' => __('Data is successfully deleted')]);

		}

		return response()->json(['success' => __('You are not authorized')]);
	}

	public function delete_by_selection(Request $request)
	{
		if (!config('app.USER_VERIFIED'))
		{
			return response()->json(['error' => 'This feature is disabled for demo!']);
		}
		$logged_user = auth()->user();

		if ($logged_user->can('delete-policy'))
		{

			$policy_id = $request['policyIdArray'];
			$policy = LatePolicy::whereIn('id', $policy_id);
			if ($policy->delete())
			{
				return response()->json(['success' => __('Multi Delete', ['key' => trans('file.Policy')])]);
			} else
			{
				return response()->json(['error' => 'Error,selected designation can not be deleted']);
			}
		}

		return response()->json(['success' => __('You are not authorized')]);
	}

	public function latePolicy()
	{
		$logged_user = auth()->user();
		$companies = company::select('id', 'company_name')->get();

		if (request()->ajax())
		{
			return datatables()->of(LatePolicy::with('company')->get())
			->setRowId(function ($policy)
			{
				return $policy->id;
			})
			->addColumn('company', function ($row)
			{
				return $row->company->company_name ?? ' ';
			})
			->addColumn('action', function ($data)
			{
				$button = '<button type="button" name="show" id="' . $data->id . '" class="show_new btn btn-success btn-sm"><i class="dripicons-preview"></i></button>';
				$button .= '&nbsp;&nbsp;';
				if (auth()->user()->can('edit-policy'))
				{
					$button = '<button type="button" name="edit" id="' . $data->id . '" class="edit btn btn-primary btn-sm"><i class="dripicons-pencil"></i></button>';
					$button .= '&nbsp;&nbsp;';
				}
				if (auth()->user()->can('delete-policy'))
				{
					$button .= '<button type="button" name="delete" id="' . $data->id . '" class="delete btn btn-danger btn-sm"><i class="dripicons-trash"></i></button>';
				}

				return $button;
			})
			->rawColumns(['action'])
			->make(true);
		}

		return view('organization.policy.late_policy', compact('companies'));
	}
}
