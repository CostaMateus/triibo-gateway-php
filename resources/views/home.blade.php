@extends( "layouts.app" )

@section( "title", "Dashboard" )

@section( "content" )
    <div class="app-content" >
        <div class="container-fluid" >
            <div class="row justify-content-center" >

                <div class="col-12 col-sm-6 col-lg-4 text-center mb-3" >
                    <div class="card bg-white" >
                        <div class="card-body" >
                            <a class="text-decoration-none" href="{{ route( "documentation" ) }}" >
                                <p class="h4 my-3 text-black-50" >
                                    <i class="bi bi-folder2-open" ></i> Documentação
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-4 text-center mb-3" >
                    <div class="card bg-white" >
                        <div class="card-body" >
                            <a class="text-decoration-none" href="{{ route( "mkt.orders" ) }}" >
                                <p class="h4 my-3 text-black-50" >
                                    <i class="bi bi-cart" ></i> Pedidos marketplace
                                </p>
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
