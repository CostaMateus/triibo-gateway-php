<!doctype html>
<html lang="{{ str_replace( '_', '-', app()->getLocale() ) }}" >
    <head>
        <meta charset="utf-8" >
        <meta http-equiv="X-UA-Compatible" content="IE=edge" >
        <meta name="viewport" content="width=device-width, initial-scale=1" >
        <meta name="csrf-token" content="{{ csrf_token() }}" >

        @if ( auth()->check() )
            <meta name="robots" content="noindex" />
        @endif

        {{-- <!-- Title --> --}}
        <title>@yield( "title" ) | Triibo</title>

        {{-- <!-- Fonts --> --}}
        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback" >

        {{-- <!-- CSS/Scripts --> --}}
        @vite( [
            "resources/sass/app.scss",
            "resources/css/app.css",
            "resources/js/app.js"
        ] )

        @yield( "css" )
    </head>
    <body class="bg-dots" >

        <div class="app-wrapper" >
            {{-- Loading --}}
            @include( "layouts.partials.load"    )

            @include( "layouts.partials.navbar"  )

            <main class="app-main py-4" >
                @yield( "content" )
            </main>
        </div>

        @if ( isset( $showToast ) && $showToast )
            <div class="position-fixed mx-auto p-3" style="z-index: 1005; top: 0; left: 0; right: 0;" >
                <div id="liveToast" class="mx-auto toast fade show" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000" >
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
                window.setTimeout( function () {
                    $( "#liveToast" ).removeClass( "show" );
                }, 2000 );
            </script>
        @endif

        @yield( "js" )
    </body>
</html>
