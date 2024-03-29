<?php

namespace App\Imports;

use App\Employee;
use App\User;
use App\company;
use App\department;
use App\designation;
use App\office_shift;
use Spatie\Permission\Models\Role;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class UsersImport implements ToModel, WithHeadingRow, ShouldQueue, WithChunkReading, WithBatchInserts, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */

    use Importable;

    public function model(array $row)
    {
        $comapny_name     =  $row['company_name'];
        $department_name  =  $row['department_name'];
        $designation_name =  $row['designation_name'];
        $shift_name       =  $row['shift_name'];
        $name             =  $row['role_name'];
        try {
            $company = company::where('company_name', $comapny_name)->pluck('id')->first();
            $department = department::where('department_name', $department_name)->where('company_id', $company)->select('id')->first();
            $designation = designation::where('designation_name', $designation_name)->where('company_id', $company)->where('department_id', $department->id)->select('id')->first();
            $office_shift = office_shift::where('shift_name', $shift_name)->where('company_id', $company)->select('id')->first();
            $role = Role::where('name', $name)->select('id')->first();

            $user = User::create([
                'first_name' => $row['full_name'],
                'username' => $row['username'],
                'email' => $row['email'],
                'password' => Hash::make($row['password']),
                'contact_no' => $row['contact_no'],
                'role_users_id' => $role->id,
                'is_active' => 1,
            ]);

            return new Employee([
                'id' => $user->id,
                'first_name' => $row['full_name'],
                'nic_no' => $row['nic_no'],
                'card_no' => $row['card_no'],
                'email' => $row['email'],
                'contact_no' => $row['contact_no'],
                'joining_date' => $row['date_of_joining'],
                'date_of_birth' => $row['date_of_birth'],
                'gender' => $row['gender'],
                'address' => $row['address'],
                'city' => $row['city'],
                'country' => $row['country'],
                'zip_code' => $row['zip'],
                'attendance_type' => 'general',
                'company_id' => $company,
                'department_id' => $department->id,
                'designation_id' => $designation->id,
                'designation_id' => $designation->id,
                'office_shift_id' => $office_shift->id,
                'role_users_id' => $role->id,
                'is_active' => 1,
            ]);
        } catch (\Throwable $e) {
            dd($e);
        }
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required',
            'nic_no' => 'required',
            'card_no' => 'required',
            'contact_no' => 'required',
            'password' => 'required',
            'username' => 'required|unique:users,username'
        ];
    }

    //	public function customValidationAttributes()
    //	{
    //		return [
    //			'email.required' => 'email',
    //		];
    //	}

    public function chunkSize(): int
    {
        return 500;
    }

    public function batchSize(): int
    {
        return 1000;
    }
}
