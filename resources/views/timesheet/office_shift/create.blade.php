@extends('layout.main')

@section('content')

    <section class="forms">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header d-flex align-items-center">
                            <h3>{{__('Add Office Shift')}}</h3>
                        </div>
                        <div class="card-body">
                            <p class="italic">
                                <small>{{__('The field labels marked with * are required input fields')}}.</small>
                            </p>
                            <form method="post" id="sample_form" class="form-horizontal">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Company')}} *</label>
                                            <select name="company_id" id="company_id" 
                                                class="form-control selectpicker dynamic" required
                                                data-live-search="true" data-live-search-style="contains"  data-first_name="first_name" data-last_name="last_name" data-dependent="department_name"
                                                title='{{__('Selecting',['key'=>trans('file.Company')])}}...'>
                                                @foreach($companies as $company)
                                                    <option value="{{$company->id}}">{{$company->company_name}}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>{{trans('file.Shift')}} *</label>
                                            <select name="shift" id="shift_id" class="form-control selectpicker" data-live-search="true" data-live-search-style="contains" title="{{__('Selecting',['key'=>trans('file.Shift')])}}...">
                                                <option >{{__('Morning Shift')}}</option>
                                                <option >{{__('Night Shift')}}</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">{{__('Start Date')}} *</label>
                                            <input class="form-control month_year date" placeholder="Select Date" readonly="" id="start_date" name="start_date" type="text" required value="">
                                        </div>
                                    </div>


                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="end_date">{{__('End Date')}} *</label>
                                            <input class="form-control month_year date" placeholder="Select Date" readonly="" id="end_date" name="end_date" type="text" required value="">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label>{{__('Clock In')}} *</label>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <input type="text" name="clock_in" id="clock_in" class="form-control time mb-3" value="" placeholder="{{__('In Time')}}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label>{{__('Clock Out')}} *</label>
                                        <div class="row">
                                            <div class="col-md-12">
                                                <input type="text" name="clock_out" id="clock_out" class="form-control time mb-3" value="" placeholder="{{__('Out Time')}}">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <label>{{trans('file.Department')}}</label>
                                        <select name="department_id" id="department_id" class="selectpicker form-control department_wise_employees" data-live-search="true" data-live-search-style="contains" data-first_name="first_name" data-last_name="last_name" title="{{__('Selecting',['key'=>trans('file.Department')])}}...">
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label>{{trans('file.Employee')}} *</label>
                                        <select multiple name="employee_id[]" id="employee_id"  class="selectpicker form-control" data-live-search="true" data-live-search-style="contains" title='{{__("Selecting",["key"=>trans("file.Employee")])}}...'>
                                        </select>
                                    </div>

                                    <span id="form_result"></span>

                                    <div class="col-md-6 offset-md-3 mt-3">
                                        <div class="form-group" align="center">
                                            <input type="hidden" name="action" id="action"/>
                                            <input type="hidden" name="hidden_id" id="hidden_id"/>
                                            <input type="submit" name="action_button" id="action_button" class="btn btn-warning btn-block" value="{{trans('file.Add')}}" />
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
            todayHighlight: true
        });

        // $('.dynamic').change(function() {
        //     if ($(this).val() !== '') {
        //         let value = $(this).val();
        //         let first_name = $(this).data('first_name');
        //         let _token = $('input[name="_token"]').val();
        //         $.ajax({
        //             url:"{{ route('dynamic_employee') }}",
        //             method:"POST",
        //             data:{ value:value, _token:_token, first_name:first_name},
        //             success:function(result)
        //             {
        //                 result = '<option value="all">SELECT ALL</option>' + '' + result;
        //                 $('select').selectpicker("destroy");
        //                 $('#employee_id').html(result);
        //                 $('select').selectpicker();
        //             }
        //         });
        //     }
        // });

        $('#sample_form').on('submit', function (event) {
            event.preventDefault();

            $.ajax({
                url: "{{ route('office_shift.store') }}",
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
                        $('#sample_form')[0].reset();
                        $('select').selectpicker('refresh');
                    }
                    $('#form_result').html(html).slideDown(300).delay(5000).slideUp(300);
                }
            })
        });

        $('.dynamic').change(function() {
            if ($(this).val() !== '') {
                let value = $(this).val();
                let first_name = $(this).data('first_name');
                let last_name = $(this).data('last_name');
                let _token = $('input[name="_token"]').val();
                $.ajax({
                    url:"{{ route('dynamic_employee') }}",
                    method:"POST",
                    data:{ value:value, _token:_token, first_name:first_name,last_name:last_name},
                    success:function(result)
                    {
                        $('select').selectpicker("destroy");
                        $('#employee_id').html(result);
                        $('select').selectpicker();

                    }
                });
            }
        });

        $('.dynamic').change(function () {
            if ($(this).val() !== '') {
                let value = $(this).val();
                let dependent = $(this).data('dependent');
                let _token = $('input[name="_token"]').val();
                $.ajax({
                    url: "{{ route('dynamic_department') }}",
                    method: "POST",
                    data: {value: value, _token: _token, dependent: dependent},
                    success: function (result) {

                        $('select').selectpicker("destroy");
                        $('#department_id').html(result);
                        $('select').selectpicker();

                    }
                });
            }
        });

        $('.department_wise_employees').change(function () {
            if ($(this).val() !== '') {
                let value = $(this).val();
                let first_name = $(this).data('first_name');
                let last_name = $(this).data('last_name');
                let _token = $('input[name="_token"]').val();
                $.ajax({
                    url: "{{ route('dynamic_employee_department') }}",
                    method: "POST",
                    data: {value: value, _token: _token, first_name:first_name,last_name:last_name},
                    success: function (result) {

                        $('select').selectpicker("destroy");
                        $('#employee_id').html(result);
                        $('select').selectpicker();

                    }
                });
            }
        });





    })(jQuery);
    
</script>
@endpush
