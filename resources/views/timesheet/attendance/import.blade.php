@extends('layout.main')
@section('content')


    <section>
        <div class="container-fluid">

            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>{{ session('message')}}!</strong>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            <div class="card">
                <div class="card-body">
                    <div class="card-title"><h3>{{__('Upload attendance log (CSV download from Zkteco software)')}}</h3></div>
                    <form method="post" action="{{route('attendances.importAttendance')}}"  class="form-horizontal" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input class="form-control @error('file') is-invalid @enderror" name="file" type="file">
                                    @error('file')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                            <div class="col-md-4">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </section>


@endsection
