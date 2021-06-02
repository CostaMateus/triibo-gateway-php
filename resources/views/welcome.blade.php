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
            body{font-family:Nunito,sans-serif;margin:0}*,:after,:before{box-sizing:border-box}.flex{display:flex}.items-center{align-items:center}.justify-center{justify-content:center}.h-100{min-height:100vh}.triibo-logo{width:300px}
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

            <script>
                // jQuery('#liveToast').toast('show');
                window.setTimeout(function () {
                    location.href = "/";
                }, 3000);
            </script>
        @endif
    </body>
</html>
