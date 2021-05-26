<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Triibo') }}</title>

        <!-- Scripts -->
        <script src="{{ asset('js/app.js') }}" defer></script>

        <!-- Styles -->
        <link href="{{ asset('css/app.css') }}" rel="stylesheet">
        <style>
            body{font-family:Nunito,sans-serif;margin:0}*,:after,:before{box-sizing:border-box}.bg-triibo{--bg-opacity:1;background-color:#6e3296;background-color:rgba(110,50,150,var(--bg-opacity))}.flex{display:flex}.items-center{align-items:center}.justify-center{justify-content:center}.h-100{min-height:100vh}.triibo-logo{width:300px}
        </style>
    </head>
    <body>
        <div class="flex justify-center bg-triibo items-center h-100">
            <img class="triibo-logo" src="{{ asset('images/logo/logo-triibo.png')}}" alt="Triibo">
        </div>

        @if ( $showToast )
            <div class="position-fixed mx-auto p-3" style="z-index: 1005; top: 0; left: 0; right: 0;">
                <div id="liveToast" class="mx-auto toast show" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000">
                    <div class="toast-header">
                        <strong class="mr-auto">Triibo</strong>
                        <small>agora</small>
                        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="toast-body">
                        {{ $message }}
                    </div>
                </div>
            </div>
            <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-Piv4xVNRyMGpqkS2by6br4gNJ7DXjqk09RmUpJ8jgGtD7zP9yug3goQfGII0yAns" crossorigin="anonymous"></script>
            <script>
                $('#liveToast').toast('show');
                window.setTimeout(function () {
                    location.href = "/";
                }, 3000);
            </script>
        @endif
    </body>
</html>
