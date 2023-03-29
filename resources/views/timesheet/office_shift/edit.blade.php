@extends('layout.main')

@section('content')

    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3>{{__('Edit Office Shift')}}</h3>
                        </div>
                        <div class="card-body">
                            <p class="italic">
                                <small>{{__('The field labels marked with * are required input fields')}}.
                                </small>
                            </p>
                            <form method="post" id="sample_form" class="form-horizontal">

                                @csrf
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{__('Company')}} *</label>
                                            <select name="company_id" id="company_id" class="form-control selectpicker" data-live-search-style="contains" title='{{__("Selecting",["key"=>trans("file.Company")])}}...' readonly="">
                                                <option selected value="{{$office_shift->company_id}}" >{{$office_shift->company->company_name}}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{__('Shift')}} *</label>
                                            <select name="shift" id="shift_id" class="form-control selectpicker" data-live-search="true" data-live-search-style="contains" title='{{__('Selecting',['key'=>trans('file.Shift')])}}...'>
                                                <option @if($office_shift->shift_name == 'Morning Shift') selected @endif >{{__('Morning Shift')}}</option>
                                                <option @if($office_shift->shift_name == 'Night Shift') selected @endif >{{__('Night Shift')}}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">{{__('Start Date')}} *</label>
                                            <input class="form-control month_year date" placeholder="Select Date" readonly="" id="start_date" name="start_date" type="text" required value="{{$office_shift->shift_date}}">
                                        </div>
                                    </div>


                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">{{__('End Date')}} *</label>
                                            <input class="form-control month_year date" placeholder="Select Date" readonly="" id="end_date" name="end_date" type="text" required value="{{$office_shift->shift_date}}">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label>{{__('Clock In')}} *</label>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <input type="text" name="clock_in" id="clock_in" class="form-control time mb-3" placeholder="{{__('In Time')}}" value="{{$office_shift->clock_in}}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label>{{__('Clock Out')}} *</label>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <input type="text" name="clock_out" id="clock_out" class="form-control time mb-3"  placeholder="{{__('Out Time')}}" value="{{$office_shift->clock_out}}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label>{{__('Employee')}} *</label>
                                            <select name="employee_id" id="employee_id" class="form-control selectpicker" title='{{__("Selecting",["key"=>trans("file.Employee")])}}...' readonly="">
                                                <option selected value="{{$office_shift->employee_id}}" >{{$office_shift->employee->full_name}} - {{$office_shift->employee->card_no}}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <span id="form_result"></span>

                                    <div class="col-md-6 offset-md-3 mt-3">
                                        <div class="form-group" align="center">
                                            <input type="hidden" name="hidden_id" id="hidden_id" value="{{$office_shift->id}}"/>
                                            <input type="submit" name="action_button" id="action_button" class="btn btn-warning btn-block" value={{trans('file.Update')}} />
                                        </div>
                                    </div>
                                </div>

                            </form>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


@endsection

@push('scripts')
<script>

    (function($) {
        "use strict";
        $('.time').clockpicker({
            placement: 'top',
            align: 'left',
            donetext: 'done',
            twelvehour: true,
        });

        let date = $('.date');
        
        date.datepicker({
            format: "{{config('app.Date_Format_JS')}}",
            autoclose: true,
            todayHighlight: true,
            endDate: new Date()
        });

        $('.dynamic').change(function() {
            if ($(this).val() !== '') {
                let value = $(this).val();
                let first_name = $(this).data('first_name');
                let _token = $('input[name="_token"]').val();
                $.ajax({
                    url:"{{ route('dynamic_employee') }}",
                    method:"POST",
                    data:{ value:value, _token:_token, first_name:first_name},
                    success:function(result)
                    {
                        $('select').selectpicker("destroy");
                        $('#employee_id').html(result);
                        $('select').selectpicker();

                    }
                });
            }
        });

        $('#sample_form').on('submit', function (event) {
            event.preventDefault();

                $.ajax({
                    url: "{{ route('office_shift.update') }}",
                    method: "POST",
                    data: new FormData(this),
                    contentType: false,
                    cache: false,
                    processData: false,
                    dataType: "json",
                    success: function (data) {
                        var html = '';
                        if (data.errors) {
                            html = '<div class="alert alert-danger">';
                            for (var count = 0; count < data.errors.length; count++) {
                                html += '<p>' + data.errors[count] + '</p>';
                            }
                            html += '</div>';
                        }
                        if (data.success) {
                            html = '<div class="alert alert-success">' + data.success + '</div>';
                            // $('#sample_form')[0].reset();
                            // $('select').selectpicker('refresh');
                        }
                        $('#form_result').html(html).slideDown(300).delay(5000).slideUp(300);
                    }
                })
        });

    })(jQuery);
</script>
@endpush
