@extends('layouts.app')

@section('custom_css')
    <style>
        table tr td {
            vertical-align: middle!important;
        }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('common.user.management') }}</div>

                    <div class="card-body">
                        <button class="btn btn-success btn-sm mb-3 add-user" >{{ __('common.user.create') }}</button>

                        @if ($message = Session::get('success'))
                        <div class="alert alert-success">
                            <p>{{ $message }}</p>
                        </div>
                        @endif

                        <table class="table table-borderless table-sm mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th                     >{{ __('common.name') }}</th>
                                    <th                     >{{ __('common.email') }}</th>
                                    <th class="text-center" >{{ __('common.actions') }}</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ($data as $key => $user)
                                <tr id="user-{{ $user->id }}">
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $user->name }}</td>
                                    <td>{{ $user->email }}</td>
                                    <td class="text-center d-flex justify-content-center" >
                                        <button class="btn btn-link text-primary edit-user" title="{{ __('common.edit') }}"   data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}" >
                                            <x-bi-pencil-square />
                                        </button>
                                        <button class="btn btn-link text-danger del-user"   title="{{ __('common.delete') }}" data-id="{{ $user->id }}" data-name="{{ $user->name }}" >
                                            <x-bi-trash />
                                        </button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="add-user" class="modal fade " tabindex="-1" role="dialog" aria-labelledby="add-user-title" aria-hidden="true" >
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ __('common.user.create') }}
                    </h5>
                    <button type="button" class="close my-auto py-0" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="add-user-form" action="" method="" >
                        @csrf
                        <div class="row">
                            <div class="col-12 col-md-6 pe-md-2">
                                <div class="form-group">
                                    <label for="add_name" class="text-muted">{{ __('common.name') }}</label>
                                    <input type="text" id="add_name" class="form-control form-control-sm" name="name" >
                                    <span id="span_add_name" class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2">
                                <div class="form-group">
                                    <label for="add_email" class="text-muted">{{ __('common.email') }}</label>
                                    <input type="text" id="add_email" class="form-control form-control-sm" name="email" >
                                    <span id="span_add_email" class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 pe-md-2">
                                <div class="form-group">
                                    <label for="add_password" class="text-muted">{{ __('common.user.password') }}</label>
                                    <input type="password" id="add_password" class="form-control form-control-sm" name="password" autocomplete="off" >
                                    <span id="span_add_password" class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2">
                                <div class="form-group">
                                    <label for="add_password_confirmation" class="text-muted">{{ __('common.user.confirm_pass') }}</label>
                                    <input type="password" id="add_password_confirmation" class="form-control form-control-sm" name="password_confirmation" autocomplete="off" >
                                    <span id="span_add_password_confirmation" class="invalid-feedback"></span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 ps-2 ms-auto">
                                <button type="submit" class="btn btn-success btn-sm btn-block" >{{ __('common.confirm') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="edit-user" class="modal fade " tabindex="-1" role="dialog" aria-labelledby="edit-user-title" aria-hidden="true" >
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ __('common.user.edit') }}
                        <span id="edit-user-title" class="text-primary"></span>
                    </h5>
                    <button type="button" class="close my-auto py-0" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="edit-user-form" action="" method="" >
                        @csrf
                        <div class="row">
                            <div class="col-12 col-md-6 pe-md-2">
                                <div class="form-group">
                                    <label for="edit_name" class="text-muted">{{ __('common.name') }}</label>
                                    <input type="text" id="edit_name" class="form-control form-control-sm" name="name" >
                                    <span id="span_edit_name" class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2">
                                <div class="form-group">
                                    <label for="edit_email" class="text-muted">{{ __('common.edit') }}</label>
                                    <input type="text" id="edit_email" class="form-control form-control-sm" name="email" >
                                    <span id="span_edit_email" class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 pe-md-2">
                                <div class="form-group">
                                    <label for="edit_password" class="text-muted">{{ __('common.user.password') }}</label>
                                    <input type="password" id="edit_password" class="form-control form-control-sm" name="password" autocomplete="off" >
                                    <span id="span_edit_password" class="invalid-feedback"></span>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2">
                                <div class="form-group">
                                    <label for="edit_password_confirmation" class="text-muted">{{ __('common.user.confirm_pass') }}</label>
                                    <input type="password" id="edit_password_confirmation" class="form-control form-control-sm" name="password_confirmation" autocomplete="off" >
                                    <span id="span_edit_password_confirmation" class="invalid-feedback"></span>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-6 ps-2 ms-auto">
                                <button type="submit" class="btn btn-success btn-sm btn-block" >{{ __('common.save') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="del-user" class="modal fade teste" tabindex="-1" role="dialog" aria-labelledby="del-user-title" aria-hidden="true" >
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        {{ __('common.user.delete') }}
                        <span id="del-user-title" class="text-danger"></span>
                    </h5>
                    <button type="button" class="close my-auto py-0" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="del-user-form" action="" method="" >
                        @csrf
                        <div class="row">
                            <div class="col-12 text-center">
                                <p>{{ __('common.user.confirm_delete') }}</p>
                            </div>
                            <div class="col-3 mx-auto">
                                <span id="span_delete" class="mb-2 invalid-feedback"></span>
                                <button type="submit" class="btn btn-danger btn-sm btn-block" >{{ __('common.confirm') }}</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('custom_js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // CREATE USER
            $(document).on('click', '.add-user', function() {
                clearFieldInvalidSpan('add');
                $('#add-user').modal('show');
            });
            $('#add-user-form').on('submit', function(e) {
                e.preventDefault();
                const id   = $(this).data('id');
                const url  = '{{ url("/users") }}';
                const data = $('#add-user-form').serialize();

                $('#add-user-form button').html('<div class="spinner-border spinner-border-sm text-light" role="status"></div>').prop('disabled', 'disabled');

                $.ajax({
                    type: 'POST',
                    url : url,
                    data: data,
                    success: function (data) {
                        location.reload();
                    },
                    error: function (error) {
                        // console.log(error);
                        $('#add-user-form button').html('{{ __("common.confirm") }}').prop('disabled', false);
                        if ( error.status == 422 ) showErrors( error, 'add' );
                    }
                });
            });

            // EDIT USER
            $(document).on('click', '.edit-user', function() {
                clearFieldInvalidSpan('edit');
                $('#edit-user-title').html($(this).data('name'));
                $('#edit-user-form').attr('data-id', $(this).data('id'));
                $('#edit-user-form #edit_name').val($(this).data('name'));
                $('#edit-user-form #edit_email').val($(this).data('email'));
                $('#edit-user').modal('show');
            });
            $('#edit-user-form').on('submit', function(e) {
                e.preventDefault();
                const id   = $(this).data('id');
                const url  = '{{ url("/users") }}' + '/' + id;
                const data = $('#edit-user-form').serialize();

                $('#edit-user-form button').html('<div class="spinner-border spinner-border-sm text-light" role="status"></div>').prop('disabled', 'disabled');

                $.ajax({
                    type: 'PATCH',
                    url : url,
                    data: data,
                    success: function (data) {
                        location.reload();
                    },
                    error: function (error) {
                        // console.log(error);
                        $('#edit-user-form button').html('{{ __("common.save") }}').prop('disabled', false);
                        if ( error.status == 422 ) showErrors( error, 'edit' );
                    }
                });
            });

            // DELETE USER
            $(document).on('click', '.del-user', function() {
                $('#del-user-title').html($(this).data('name'));
                $('#del-user-form').attr('data-id', $(this).data('id'));
                $('#del-user').modal('show');
            });
            $('#del-user-form').on('submit', function(e) {
                e.preventDefault();
                const id   = $(this).data('id');
                const url  = '{{ url("/users") }}' + '/' + id;
                const data = $('#del-user-form').serialize();

                $('#del-user-form button').html('<div class="spinner-border spinner-border-sm text-light" role="status"></div>').prop('disabled', 'disabled');

                $.ajax({
                    type: 'DELETE',
                    url : url,
                    data: data,
                    success: function (data) {
                        location.reload();
                    },
                    error: function (error) {
                        console.log(error.statusText);
                        $('#del-user-form button').html('{{ __("common.confirm") }}').prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endsection
