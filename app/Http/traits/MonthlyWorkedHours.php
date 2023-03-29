<?php

namespace App\Http\traits;

Trait MonthlyWorkedHours {

	public function totalWorkedHours($employee)
	{
		if($employee->employeeAttendance->isEmpty()){
			
			return 0;
		
		}else{

			$total = 0;
			$tHour = 0;
			$tMin = 0;

			$tHour_w = 0;
			$tMin_w = 0;

			$tHour_l = 0;
			$tMin_l = 0;
			
			foreach ($employee->employeeAttendance as $a)
			{
				sscanf($a->total_work, '%d:%d', $hour, $min);

				$tHour_w += $hour;
				$tMin_w += $min;

				sscanf($a->time_late, '%d:%d', $hour, $min);
				
				$tHour_l += $hour;
				$tMin_l += $min;
				
			}

			$tHourMin = ($tHour_w - $tHour_l) * 60;
			$tMin = $tMin_w - $tMin_l;

			$tMin = $tMin + $tHourMin;

			$tHourMin = $tMin / 60;

			$tHour = floor($tHourMin);
			$tMin = floor(($tHourMin - $tHour) * 60);

			$timeHour = $tHour + ($tMin / 60);

			return ['time' => floatval($timeHour), 'str' => sprintf('%02d:%02d', $tHour, $tMin)];
		}
	}

	public function totalWorkedHoursWithoutOvertime($employee)
	{
		if($employee->employeeAttendance->isEmpty()){
			return 0;
		}else{

			$tHour_w = 0;
			$tMin_w = 0;

			$tHour_o = 0;
			$tMin_o = 0;
			
			$tHour_l = 0;
			$tMin_l = 0;

			$tHour = 0;
			$tMin = 0;


			foreach ($employee->employeeAttendance as $a)
			{
				sscanf($a->total_work, '%d:%d', $hour, $min);

				$tHour_w += $hour;
				$tMin_w += $min;
				
				sscanf($a->overtime, '%d:%d', $hour, $min);

				$tHour_o += $hour;
				$tMin_o += $min;
				
				sscanf($a->time_late, '%d:%d', $hour, $min);
				
				$tHour_l += $hour;
				$tMin_l += $min;
			}

            $tHourMin = ($tHour_w - $tHour_o - $tHour_l) * 60;
            $tMin = $tMin_w - $tMin_o - $tMin_l;

            $tMin = $tMin + $tHourMin;

			$tHourMin = $tMin / 60;

			$tHour = floor($tHourMin);
			$tMin = floor(($tHourMin - $tHour) * 60);
			
			$timeHour = $tHour + ($tMin / 60);
			
			return ['time' => floatval($timeHour), 'str' => sprintf('%02d:%02d', $tHour, $tMin)];
		}
	}
	
    //New
    public function totalOvertimeHours($employee)
	{
		if($employee->employeeAttendance->isEmpty()){
			return 0;
		}else{

			$tHour = 0;
			$tMin = 0;

			foreach ($employee->employeeAttendance as $a)
			{
				sscanf($a->overtime, '%d:%d', $hour, $min);
				$tHour += $hour;
				$tMin += $min;
			}

			if(isset($employee->employeeOverTimeAttendance)){
				
				foreach ($employee->employeeOverTimeAttendance as $a)
				{
					sscanf($a->overtime, '%d:%d', $hour, $min);
					$tHour += $hour;
					$tMin += $min;
				}
			}

			$timeHour = $tHour + ($tMin / 60);
			$tHour += floor($tMin / 60);
    		$tMin = $tMin % 60;

			return ['time' => floatval($timeHour), 'str' => sprintf('%02d:%02d', $tHour, $tMin)];
		}
	}
}
