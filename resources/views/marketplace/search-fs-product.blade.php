@extends( view: "layouts.app" )

@section( section: "title", content: "Consulta produtos FS" )

@section( section: "css" )
    <style>
        #searched pre {
            margin-bottom: 0;
        }
    </style>
@endsection

@section( section: "content" )
    <div class="container" >
        <div class="row justify-content-center" >
            <div class="col-12" >
                <h2 class="text-left" >Consulta produtos FS</h2>

                <div class="card" >
                    <div class="card-body" >

                        @include( view: "partials.alerts" )

                        <form id="search_product" class="row mb-0" action="" method="POST" >
                            @csrf

                            <div class="col-12 col-md-6 col-lg-3 mb-0" >
                                <label for="orders-file" class="text-muted" >ID do produto Fast Shop</label>
                                <input type="number" class="form-control" name="product_id" required >
                            </div>
                        </form>

                        @if ( isset( $data ) && $data )
                            <div id="searched" class="w-100 mt-3" >

                                @if ( !empty( $data[ "mkt" ] ) && is_array( value: $data[ "mkt" ] ) )
                                    <p class="mb-0" >
                                        <strong>Nome</strong>:
                                        {{ $data[ "mkt" ][ "name"         ] }}
                                    </p>
                                    <p class="mb-0" >
                                        <strong>SKU</strong>:
                                        {{ $data[ "mkt" ][ "sku"          ] }}
                                    </p>
                                    <p>
                                        <strong>Estoque</strong>:
                                        {{ $data[ "mkt" ][ "stock_status" ] }}
                                    </p>
                                @else
                                    <p class="mb-0" >Produto não encontrado na Fast Shop</p>
                                @endif

                                @dump( $data )
                            </div>
                        @endif

                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@section( section: "js" )
    <script type="module" >
        $( document ).ready( function()
        {
            $( "#search_product" ).on( "submit", function()
            {
                $( "#load" ).removeClass( "d-none" );
            } );
        } );
    </script>
@endsection
