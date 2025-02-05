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
            @include( "layouts.partials.navbar"  )

            <main class="app-main py-4" >
                @yield( "content" )
            </main>

        </div>

        @yield( "js" )
    </body>
</html>
