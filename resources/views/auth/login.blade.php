@extends( view: "layouts.auth" )

@section( section: "content" )
    <div class="container-fluid vh-100" >
        <div class="row vh-100" >
            <div class="col-12 col-sm-8 col-md-6 col-lg-4 col-xl-3 col-xxl-2 mx-auto my-auto" >

                <div class="card" >
                    <div class="card-body" >
                        <form method="POST" action="{{ route( name: "login" ) }}" >

                            @csrf

                            <div class="mb-3" >
                                <label for="email" class="form-label" >{{ __( key: "E-Mail Address" ) }}</label>
                                <input id="email" type="email" class="form-control @error( "email" ) is-invalid @enderror" name="email" value="{{ old( key: "email" ) }}" required autocomplete="email" autofocus >
                                @error( "email" )
                                    <span class="invalid-feedback" role="alert" >
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="mb-3" >
                                <label for="password" class="form-label" >{{ __( key: "Password" ) }}</label>
                                <input id="password" type="password" class="form-control @error( "password" ) is-invalid @enderror" name="password" required autocomplete="current-password" >
                                @error( "password" )
                                    <span class="invalid-feedback" role="alert" >
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>

                            <div class="mb-3" >
                                <div class="form-check" >
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old( key: "remember" ) ? "checked" : "" }}>
                                    <label class="form-check-label" for="remember" >
                                        {{ __( key: "Remember Me" ) }}
                                    </label>
                                </div>
                            </div>

                            <div class="mb-0" >
                                @if ( Route::has( name: "password.request" ) )
                                    <a class="btn btn-link float-end px-2" href="{{ route( name: "password.request" ) }}" >
                                        {{ __( key: "Forgot Your Password?" ) }}
                                    </a>
                                @endif

                                <button type="submit" class="btn btn-primary text-white" >{{ __( key: "Login" ) }}</button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection

@section( section: "js" )
    <script type="module" >
        document.addEventListener( "DOMContentLoaded", function ()
        {
            const divSpinner = "<div class='spinner-border spinner-border-sm text-light align-middle' role='status' ></div>";

            $( "form" ).on( "submit", function( e )
            {
                $( "form button" ).html( divSpinner ).prop( "disabled", "disabled" );
            } );
        } );
    </script>
@endsection
