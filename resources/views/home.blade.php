@extends( view: "layouts.app" )

@section( section: "title", content: "Dashboard" )

@section( section: "content" )
    <div class="app-content" >
        <div class="container-fluid" >
            <div class="row justify-content-center" >

                <div class="col-12 col-sm-6 col-md-5 col-lg-4 col-xl-3 text-center mb-4" >
                    <div class="card bg-white" >
                        <div class="card-body" >
                            <a class="text-decoration-none" href="{{ route( name: "users.index" ) }}" >
                                <p class="h4 my-3 text-black-50" >
                                    <i class="bi bi-people" ></i> Usuários
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-5 col-lg-4 col-xl-3 text-center mb-4" >
                    <div class="card bg-white" >
                        <div class="card-body" >
                            <a class="text-decoration-none" href="{{ route( name: "log-viewer.index" ) }}" >
                                <p class="h4 my-3 text-black-50" >
                                    <i class="bi bi-folder2-open" ></i> Logs
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center" >
                <div class="col-12 col-sm-6 col-md-5 col-lg-4 col-xl-3 text-center mb-4" >
                    <div class="card bg-white" >
                        <div class="card-body" >
                            <a class="text-decoration-none" href="{{ route( name: "docs.index" ) }}" >
                                <p class="h4 my-3 text-black-50" >
                                    <i class="bi bi-journal-text" ></i> Documentação
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-md-5 col-lg-4 col-xl-3 text-center mb-4" >
                    <div class="card bg-white" >
                        <div class="card-body" >
                            <a class="text-decoration-none" href="{{ route( name: "mkt.index" ) }}" >
                                <p class="h4 my-3 text-black-50" >
                                    <i class="bi bi-cart" ></i> Pedidos marketplace
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row justify-content-center" >
                <div class="col-12 col-sm-6 col-md-5 col-lg-4 col-xl-3 text-center mb-4" >
                    <div class="card bg-white" >
                        <div class="card-body" >
                            <a class="text-decoration-none" href="{{ route( name: "mkt.search.products" ) }}" >
                                <p class="h4 my-3 text-black-50" >
                                    <i class="bi bi-search" ></i> Consulta produtos FS
                                </p>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection
