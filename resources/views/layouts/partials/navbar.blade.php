<nav class="navbar navbar-expand-md navbar-light bg-white shadow-sm" >
    <div class="container-fluid" >
        <a class="navbar-brand" href="{{ route( name: "welcome" ) }}" >
            {{ config( key: "app.name", default: "Triibo" ) }}
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="{{ __( key: "Toggle navigation" ) }}" >
            <span class="navbar-toggler-icon" ></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarSupportedContent" >
            {{-- <!-- Left Side Of Navbar --> --}}
            <ul class="navbar-nav me-auto" >
                <li class="nav-item" ><a class="nav-link" href="{{ route( name: "home" ) }}" >Início</a></li>
            </ul>

            {{-- <!-- Right Side Of Navbar --> --}}
            <ul class="navbar-nav ms-auto" >
                {{-- <!-- Authentication Links --> --}}
                {{-- @guest
                    @if ( Route::has( "login" ) )
                        <li class="nav-item" ><a class="nav-link" href="{{ route( "login"    ) }}" >Login</a></li>
                    @endif

                    @if ( Route::has( "register" ) )
                        <li class="nav-item" ><a class="nav-link" href="{{ route( "register" ) }}" >Registrar</a></li>
                    @endif
                @endguest --}}

                @auth
                    <li><a class="nav-link" href="{{ route( name: "users.index"      ) }}" >Usuários</a></li>
                    <li><a class="nav-link" href="{{ route( name: "log-viewer.index" ) }}" >Logs    </a></li>
                    <li>
                        <a class="nav-link" href="{{ route( name: "logout" ) }}" onclick="event.preventDefault(); document.getElementById( 'logout-form' ).submit();" >Sair</a>
                        <form id="logout-form" action="{{ route( name: "logout" ) }}" method="POST" class="d-none" > @csrf </form>
                    </li>
                @endauth
            </ul>
        </div>
    </div>
</nav>
