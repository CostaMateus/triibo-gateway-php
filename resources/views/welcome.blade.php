<!DOCTYPE html>
<html lang="{{ str_replace( search: '_', replace: '-', subject: app()->getLocale() ) }}" >
    <head>
        <meta charset="utf-8" >
        <meta http-equiv="X-UA-Compatible" content="IE=edge" >
        <meta name="viewport" content="width=device-width, initial-scale=1" >
        <meta name="csrf-token" content="{{ csrf_token() }}" >

        {{-- Title --}}
        <title>{{ config( key: "app.name", default: "Triibo" ) }}</title>

        {{-- CSS/JS --}}
        @vite( entrypoints: [
            "resources/sass/app.scss",
            "resources/css/app.css",
            "resources/js/app.js"
        ] )
    </head>
    <body>
        <div class="d-flex flex-column justify-content-center align-items-center bg-triibo vh-100" >
            <img class="triibo-logo" src="{{ asset( path: 'images/logo/logo-triibo.png' )}}" alt="Triibo" width="300" >
            <a class="btn btn-default text-triibo" href="{{ route( name: "login" ) }}" >HOME</a>
        </div>

        @if ( $showToast )
            <div class="position-fixed mx-auto p-3" style="z-index: 1005; top: 0; left: 0; right: 0;" >
                <div id="liveToast" class="mx-auto toast show" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000" >
                    <div class="toast-header" >
                        <strong class="me-auto" >Triibo</strong>
                        <small>agora</small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close" ></button>
                    </div>
                    <div class="toast-body" >
                        {{ $message }}
                    </div>
                </div>
            </div>

            <script>
                // jQuery('#liveToast').toast('show');
                window.setTimeout( function () { location.href = "/"; }, 10000 );
            </script>
        @endif
    </body>
</html>
