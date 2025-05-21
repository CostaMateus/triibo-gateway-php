@extends( view: "layouts.app" )

@section( section: "title", content: "Lista de usuários" )

@section( section: "content" )
    <div class="container" >
        <div class="row justify-content-center" >
            <div class="col-12 col-sm-10 col-md-8 col-lg-6" >
                <h2 class="text-left" >Usuários</h2>

                <div class="card" >
                    <div class="card-body" >
                        <button class="btn btn-success text-white btn-sm mb-3 add-user float-end" >Adicionar novo</button>

                        @if ( $message = Session::get( key: "success" ) )
                            <div class="alert alert-success" >
                                <p>{{ $message }}</p>
                            </div>
                        @endif

                        <table class="table table-borderless table-striped table-sm mb-0" >
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th                     >Nome</th>
                                    <th                     >E-mail</th>
                                    <th class="text-center" >Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach ( $data as $key => $user )
                                    <tr id="user-{{ $user->id }}" >
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $user->name      }}</td>
                                        <td>{{ $user->email     }}</td>

                                        <td class="text-center d-flex justify-content-center" >
                                            <button class="btn btn-link text-primary edit-user" title="Editar"
                                                data-id="{{ $user->id }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}" >
                                                <i class="bi bi-pencil-square" ></i>
                                            </button>
                                            <button class="btn btn-link text-danger del-user"   title="Excluir"
                                                data-id="{{ $user->id }}" data-name="{{ $user->name }}" >
                                                <i class="bi bi-trash" ></i>
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

    <div id="add-user" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="add-user-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" >
        <div class="modal-dialog modal-dialog-centered modal-md" >
            <div class="modal-content" >
                <div class="modal-header" >
                    <h5 id="add-user-title" class="modal-title" >Novo usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
                </div>
                <div class="modal-body" >
                    <form id="add-user-form" >
                        @csrf
                        <div class="row" >
                            <div class="col-12 col-md-6 pe-md-2" >
                                <div class="row mb-3" >
                                    <label for="add_name" class="col-12 col-form-label text-muted" >Nome</label>
                                    <div class="col-12" >
                                        <input type="text" id="add_name" class="form-control form-control-sm" name="name" >
                                        <span id="span_add_name" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2" >
                                <div class="row mb-3" >
                                    <label for="add_email" class="col-12 col-form-label text-muted" >E-mail</label>
                                    <div class="col-12" >
                                        <input type="text" id="add_email" class="form-control form-control-sm" name="email" >
                                        <span id="span_add_email" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 pe-md-2" >
                                <div class="row mb-3" >
                                    <label for="add_password" class="col-12 col-form-label text-muted" >Senha</label>
                                    <div class="col-12" >
                                        <input type="password" id="add_password" class="form-control form-control-sm" name="password" autocomplete="off" >
                                        <span id="span_add_password" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2" >
                                <div class="row mb-3" >
                                    <label for="add_password_confirmation" class="col-12 col-form-label text-muted" >Confirmação de senha</label>
                                    <div class="col-12" >
                                        <input type="password" id="add_password_confirmation" class="form-control form-control-sm" name="password_confirmation" autocomplete="off" >
                                        <span id="span_add_password_confirmation" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" >
                            <div class="col-12" >
                                <button type="submit" class="btn btn-success btn-sm btn-block text-white float-end" >Adicionar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="edit-user" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="edit-user-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" >
        <div class="modal-dialog modal-dialog-centered modal-md" >
            <div class="modal-content" >
                <div class="modal-header" >
                    <h5 class="modal-title" >
                        Editando usuário: <span id="edit-user-title" class="text-primary" ></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
                </div>
                <div class="modal-body" >
                    <form id="edit-user-form" >
                        @csrf
                        <div class="row" >
                            <div class="col-12 col-md-6 pe-md-2" >
                                <div class="row mb-3" >
                                    <label for="edit_name" class="col-12 col-form-label text-muted" >Nome</label>
                                    <div class="col-12" >
                                        <input type="text" id="edit_name" class="form-control form-control-sm" name="name" >
                                        <span id="span_edit_name" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2" >
                                <div class="row mb-3" >
                                    <label for="edit_email" class="col-12 col-form-label text-muted" >E-mail</label>
                                    <div class="col-12" >
                                        <input type="text" id="edit_email" class="form-control form-control-sm" name="email" >
                                        <span id="span_edit_email" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 pe-md-2" >
                                <div class="row mb-3" >
                                    <label for="edit_password" class="col-12 col-form-label text-muted" >Senha</label>
                                    <div class="col-12" >
                                        <input type="password" id="edit_password" class="form-control form-control-sm" name="password" autocomplete="off" >
                                        <span id="span_edit_password" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 ps-md-2" >
                                <div class="row mb-3" >
                                    <label for="edit_password_confirmation" class="col-12 col-form-label text-muted" >Confirmação de senha</label>
                                    <div class="col-12" >
                                        <input type="password" id="edit_password_confirmation" class="form-control form-control-sm" name="password_confirmation" autocomplete="off" >
                                        <span id="span_edit_password_confirmation" class="invalid-feedback" ></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" >
                            <div class="col-12" >
                                <button type="submit" class="btn btn-success btn-sm btn-block text-white float-end" >Salvar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div id="del-user" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="del-user-title" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" >
        <div class="modal-dialog modal-dialog-centered modal-md" >
            <div class="modal-content" >
                <div class="modal-header" >
                    <h5 class="modal-title" >
                        Excluir usuário: <span id="del-user-title" class="text-danger" ></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
                </div>
                <div class="modal-body" >
                    <form id="del-user-form" action="" method="" >
                        @csrf
                        <div class="row" >
                            <div class="col-12 text-start" >
                                <p>Deseja realmente excluir esse usuário?</p>
                            </div>
                            <div class="col-12" >
                                <span id="span_delete" class="mb-2 invalid-feedback" ></span>
                                <button type="submit" class="btn btn-danger btn-sm btn-block text-white float-end" >
                                    Confirmar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section( "js" )
    <script type="module" >
        document.addEventListener( "DOMContentLoaded", function ()
        {
            const divSpinner = "<div class='spinner-border spinner-border-sm text-light align-middle' role='status' ></div>";

            // CREATE USER
            $( document ).on( "click", ".add-user", function()
            {
                clearFieldInvalidSpan( "add" );
                ( new bootstrap.Modal( "#add-user" ) ).show();
            } );

            $( "#add-user-form" ).on( "submit", function( e )
            {
                e.preventDefault();

                const id   = $( this ).data( "id" );
                const url  = "{{ url( path: "/users" ) }}";
                const data = $( "#add-user-form" ).serialize();

                $( "#add-user-form button" ).html( divSpinner ).prop( "disabled", "disabled" );

                $.ajax( {
                    type: "POST",
                    url : url,
                    data: data,
                    success: function ( data ) {
                        location.reload();
                    },
                    error: function ( error ) {
                        // console.log(error);
                        $( "#add-user-form button" ).html( "{{ __( key: "common.confirm" ) }}" ).prop( "disabled", false );

                        if ( error.status == 422 )
                            showErrors( error, "add" );
                    }
                } );
            } );

            // EDIT USER
            $( document ).on( "click", ".edit-user", function()
            {
                const id    = $( this ).data( "id"    );
                const name  = $( this ).data( "name"  );
                const email = $( this ).data( "email" );

                clearFieldInvalidSpan( "edit" );
                $( "#edit-user-title"            ).html( name );
                $( "#edit-user-form"             ).attr( "data-id", id );
                $( "#edit-user-form #edit_name"  ).val( name );
                $( "#edit-user-form #edit_email" ).val( email );

                ( new bootstrap.Modal( "#edit-user" ) ).show();
            });

            $( "#edit-user-form" ).on( "submit", function( e )
            {
                e.preventDefault();

                const id   = $( this ).data( "id" );
                const url  = `{{ url( path: "/users" ) }}/${id}`;
                const data = $( "#edit-user-form" ).serialize();

                $( "#edit-user-form button" ).html( divSpinner ).prop( "disabled", "disabled" );

                $.ajax( {
                    type: "PATCH",
                    url : url,
                    data: data,
                    success: function ( data ) {
                        location.reload();
                    },
                    error: function ( error ) {
                        // console.log(error);
                        $( "#edit-user-form button" ).html( "{{ __( key: 'common.save' ) }}" ).prop( "disabled", false );

                        if ( error.status == 422 )
                            showErrors( error, "edit" );
                    }
                } );
            } );

            // DELETE USER
            $( document ).on( "click", ".del-user", function()
            {
                const id   = $( this ).data( "id"   );
                const name = $( this ).data( "name" );

                $( "#del-user-title" ).html( name );
                $( "#del-user-form"  ).attr( "data-id", id );

                ( new bootstrap.Modal( "#del-user" ) ).show();
            } );

            $( "#del-user-form" ).on( "submit", function( e )
            {
                e.preventDefault();

                const id   = $( this ).data( "id" );
                const url  = `{{ url( path: "/users" ) }}/${id}`;
                const data = $( "#del-user-form" ).serialize();

                $( "#del-user-form button" ).html( divSpinner ).prop( "disabled", "disabled" );

                $.ajax( {
                    type: "DELETE",
                    url : url,
                    data: data,
                    success: function ( data ) {
                        location.reload();
                    },
                    error: function ( error ) {
                        // console.log(error.statusText);
                        $( "#del-user-form button" ).html( "{{ __( key: "common.confirm" ) }}" ).prop( "disabled", false );
                    }
                } );
            } );
        } );
    </script>
@endsection
