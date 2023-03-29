@extends('layout.main')
@section('content')



    <section>

        <div class="container-fluid">
            <div class="card mb-4">
                <div class="card-header with-border">
                    <h3 class="card-title text-center"> {{__('Office Shift')}} </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12">
                            <form method="post" id="filter_form" class="form-horizontal">
                                @csrf
                                <div class="row">

                                    @if ((Auth::user()->can('view-attendance')))
                                    {{-- @if (Auth::user()->role_users_id==1) --}}

                                        <div class="col-md-4">
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

                                        <div class="col-md-4 form-group">
                                            <label>{{trans('file.Department')}}</label>
                                            <select name="department_id" id="department_id"
                                                class="selectpicker form-control department_wise_employees"
                                                data-live-search="true" data-live-search-style="contains"
                                                data-first_name="first_name" data-last_name="last_name"
                                                title="{{__('Selecting',['key'=>trans('file.Department')])}}...">
                                            </select>
                                        </div>

                                        <div class="col-md-4 form-group">
                                            <label>{{trans('file.Employee')}} </label>
                                            <select name="employee_id" id="employee_id"  class="selectpicker form-control"
                                                    data-live-search="true" data-live-search-style="contains"
                                                    title='{{__('Selecting',['key'=>trans('file.Employee')])}}...'>
                                            </select>
                                        </div>

                                    @else
                                        <input type="hidden" name="employee_id" id="employee_id" value="{{Auth::user()->id}}"> {{-- users.id == employees.id  are same in this system--}}
                                    @endif

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">{{__('Start Date')}}</label>
                                            <input class="form-control month_year date"
                                                   placeholder="Select Date" readonly=""
                                                   id="start_date" name="start_date" type="text" required
                                                   value="">
                                        </div>
                                    </div>


                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="start_date">{{__('End Date')}}</label>
                                            <input class="form-control month_year date"
                                                   placeholder="Select Date" readonly=""
                                                   id="end_date" name="end_date" type="text" required
                                                   value="">
                                        </div>
                                    </div>

                                </div>

                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <div class="form-actions">
                                                <button type="submit" class="filtering btn btn-primary"><i class="fa fa-search"></i> {{trans('file.Search')}}
                                                </button>
                                                @can('store-office_shift')
                                                    <a class="btn btn-info" id="create_record" href="{{route('office_shift.create')}}"><i class="fa fa-plus"></i> {{__('Add Office Shift')}}</a>
                                                @endcan
                                                @can('delete-office_shift')
                                                    <button type="button" class="btn btn-danger" name="bulk_delete" id="bulk_delete"><i class="fa fa-minus-circle"></i> {{__('Bulk delete')}}</button>
                                                @endcan
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table id="office_shift-table" class="table ">
                <thead>
                <tr>
                    <th class="not-exported"></th>
                    <th>{{trans('file.Company')}}</th>
                    <th>{{trans('file.Shift')}}</th>
                    <th>{{trans('file.Employee')}}</th>
                    <th>{{__('Card Number')}}</th>
                    <th>{{trans('file.Date')}}</th>
                    <th>{{__('Clock In')}}</th>
                    <th>{{__('Clock Out')}}</th>

                    <th class="not-exported">{{trans('file.action')}}</th>
                </tr>
                </thead>

            </table>
        </div>

    </section>

    <div id="confirmModal" class="modal fade" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">{{trans('file.Confirmation')}}</h2>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <h4 align="center">{{__('Are you sure you want to remove this data?')}}</h4>
                </div>
                <div class="modal-footer">
                    <button type="button" name="ok_button" id="ok_button" class="btn btn-danger">{{trans('file.OK')}}'
                    </button>
                    <button type="button" class="close btn-default"
                            data-dismiss="modal">{{trans('file.Cancel')}}</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script type="text/javascript">

    "use strict";
    $(document).ready(function () {


        let date = $('.date');
        date.datepicker({
            format: "{{config('app.Date_Format_JS')}}",
            autoclose: true,
            todayHighlight: true,
            endDate: new Date()
        });
    });

        fill_datatable();

        function fill_datatable() {

            $('#office_shift-table').DataTable({
                responsive: true,
                fixedHeader: {
                    header: true,
                    footer: true
                },
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ route('office_shift.index') }}",
                    data: {
                        filter_start_date: $('#start_date').val(),
                        filter_end_date: $('#end_date').val(),
                        company_id: $("#company_id").val(),
                        department_id: $("#department_id").val(),
                        employee_id: $("#employee_id").val(),
                        "_token": "{{ csrf_token()}}"
                    }
                },
                initComplete: function () {
                    this.api().columns([2, 4]).every(function () {
                        var column = this;
                        var select = $('<select><option value=""></option></select>')
                            .appendTo($(column.footer()).empty())
                            .on('change', function () {
                                var val = $.fn.dataTable.util.escapeRegex(
                                    $(this).val()
                                );

                                column
                                    .search(val ? '^' + val + '$' : '', true, false)
                                    .draw();
                            });

                        column.data().unique().sort().each(function (d, j) {
                            select.append('<option value="' + d + '">' + d + '</option>');
                            $('select').selectpicker('refresh');
                        });
                    });
                },

                columns: [

                    {
                        data: 'id',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'company',
                        name: 'company',

                    },
                    {
                        data: 'shift_name',
                        name: 'shift_name',
                    },
                    {
                        data: 'employee',
                        name: 'employee',
                    },
                    {
                        data: 'card_no',
                        name: 'card_no',
                    },
                    {
                        data: 'date',
                        name: 'date',
                    },
                    {
                        data: 'clock_in',
                        name: 'clock_in',
                    },
                    {
                        data: 'clock_out',
                        name: 'clock_out',
                    },
                    {
                        data: 'action',
                        name: 'action',
                        orderable: false
                    },
                ],
                "order": [],
                'language': {
                    'lengthMenu': '_MENU_ {{__("records per page")}}',
                    "info": '{{trans("file.Showing")}} _START_ - _END_ (_TOTAL_)',
                    "search": '{{trans("file.Search")}}',
                    'paginate': {
                        'previous': '{{trans("file.Previous")}}',
                        'next': '{{trans("file.Next")}}'
                    }
                },
                'columnDefs': [
                    {
                        "orderable": false,
                        'targets': [0, 4]
                    },
                    {
                        'render': function (data, type, row, meta) {
                            if (type == 'display') {
                                data = '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>';
                            }

                            return data;
                        },
                        'checkboxes': {
                            'selectRow': true,
                            'selectAllRender': '<div class="checkbox"><input type="checkbox" class="dt-checkboxes"><label></label></div>'
                        },
                        'targets': [0]
                    }
                ],
                'select': {style: 'multi', selector: 'td:first-child'},
                'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
                dom: '<"row"lfB>rtip',
                buttons: [
                    {
                        extend: 'pdf',
                        text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                        exportOptions: {
                            columns: ':visible:Not(.not-exported)',
                            rows: ':visible'
                        },
                    },
                    {
                        extend: 'csv',
                        text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                        exportOptions: {
                            columns: ':visible:Not(.not-exported)',
                            rows: ':visible'
                        },
                    },
                    {
                        extend: 'print',
                        text: '<i title="print" class="fa fa-print"></i>',
                        exportOptions: {
                            columns: ':visible:Not(.not-exported)',
                            rows: ':visible'
                        },
                    },
                    {
                        extend: 'colvis',
                        text: '<i title="column visibility" class="fa fa-eye"></i>',
                        columns: ':gt(0)'
                    },
                ],
            });
        }



        $('#create_record').on('click', function () {

            $('.modal-title').text('{{__('Add Office Shift')}}');
            $('#action_button').val('{{trans("file.Add")}}');
            $('#action').val('{{trans("file.Add")}}');
            $('#formModal').modal('show');


        });

        let delete_id;

        $(document).on('click', '.delete', function () {
            delete_id = $(this).attr('id');
            $('#confirmModal').modal('show');
            $('.modal-title').text('{{__('DELETE Record')}}');
            $('#ok_button').text('{{trans('file.OK')}}');

        });

        $(document).on('click', '#bulk_delete', function () {

            var id = [];
            let table = $('#office_shift-table').DataTable();
            id = table.rows({selected: true}).ids().toArray();
            if (id.length > 0) {
                if (confirm("Are you sure you want to delete the selected Office Shifts?")) {
                    $.ajax({
                        url: '{{route('mass_delete_office_shifts')}}',
                        method: 'POST',
                        data: {
                            officeShiftIdArray: id
                        },
                        success: function (data) {
                            let html = '';
                            if (data.success) {
                                html = '<div class="alert alert-success">' + data.success + '</div>';
                            }
                            if (data.error) {
                                html = '<div class="alert alert-danger">' + data.error + '</div>';
                            }
                            table.ajax.reload();
                            table.rows('.selected').deselect();
                            if (data.errors) {
                                html = '<div class="alert alert-danger">' + data.error + '</div>';
                            }
                            $('#general_result').html(html).slideDown(300).delay(5000).slideUp(300);

                        }

                    });
                }
            } else {
                alert('{{__('Please select atleast one checkbox')}}');
            }
        });

        $('#close').on('click', function () {
            $('#sample_form')[0].reset();
            fill_datatable();
            $('select').selectpicker('refresh');
        });

        $('#ok_button').on('click', function () {
            let target = "{{ route('office_shift.index') }}/" + delete_id + '/delete';
            $.ajax({
                url: target,
                beforeSend: function () {
                    $('#ok_button').text('{{trans('file.Deleting...')}}');
                },
                success: function (data) {
                    let html = '';
                    if (data.success) {
                        html = '<div class="alert alert-success">' + data.success + '</div>';
                    }
                    if (data.error) {
                        html = '<div class="alert alert-danger">' + data.error + '</div>';
                    }
                    setTimeout(function () {
                        $('#general_result').html(html).slideDown(300).delay(5000).slideUp(300);
                        $('#confirmModal').modal('hide');
                        fill_datatable();
                    }, 2000);
                }
            })
        });

        $('#filter_form').on('submit',function (e) {
            e.preventDefault();
            let filter_start_date = $('#start_date').val();
            let filter_end_date = $('#end_date').val();
            let company_id = $('#company_id').val();

            if (filter_start_date !== '' && filter_end_date !== '' && company_id !== '') {
                $('#office_shift-table').DataTable().destroy();

                fill_datatable();
            } else {
                alert('{{__('Select Both filter option')}}');
            }
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

</script>
@endpush
