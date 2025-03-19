@extends( "layouts.app" )

@section( "title", "Pedidos marketplace" )

@section( "content" )
    <div class="container-fluid" >
        <div class="row justify-content-center" >
            <div class="col-12" >
                <h2 class="text-left" >Pedidos</h2>

                <div class="card" >
                    <div class="card-body" >
                        @php
                            $type    = false;
                            $message = "";

                            if ( Session::has( "error" ) )
                            {
                                $type    = "danger";
                                $message = Session::get( "error" );
                            }

                            if ( Session::has( "success" ) )
                            {
                                $type    = "success";
                                $message = Session::get( "success" );
                            }

                            if ( Session::has( "warning" ) )
                            {
                                $type    = "warning";
                                $message = Session::get( "warning" );
                            }
                        @endphp

                        @if ( $type !== false )
                            <div class="row dismiss-alert" >
                                <div class="col-12 col-md-6 mx-auto" >
                                    <div class="alert alert-{{ $type }}" >
                                        <p class="mb-0" >{{ $message }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="row mb-3" >
                            <div class="col-12 col-md-6 mx-auto" >
                                <form id="import-form" action="{{ route( "mkt.orders.import" ) }}" method="POST" enctype="multipart/form-data" >
                                    @csrf
                                    <div class="input-group" >
                                        <input type="file" class="form-control border-secondary text-secondary" name="csv_file" accept=".csv" required >
                                        <button class="btn btn-outline-secondary" type="submit" >Importar arquivo</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="row mb-3" >
                            <div class="col-6 pe-2" >
                                <select id="orders-file" class="form-select" >
                                    <option value="" >Selecione um arquivo...</option>
                                    @foreach( $files as $f )
                                        <option value="{{ $f }}" @if ( $f == $file ) selected @endif >{{ $f }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6 ps-2" >
                                <select id="orders-status" class="form-select" >
                                    <option value="" >Selecione um status...</option>
                                    @foreach ( $statuses as $key => $value )
                                        <option value="{{ $key }}" @if ( $key == $status ) selected @endif >{{ $value }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3" >
                            <div class="col-12" >
                                <small>Legenda</small>
                            </div>
                            <div class="col-12" >
                                <span class="me-3" title="Criar" >
                                    <i class="h4 bi bi-plus-circle my-auto text-primary" ></i> Criar
                                </span>
                                <span class="me-3" title="Aprovar" >
                                    <i class="h4 bi bi-check-circle my-auto text-success" ></i> Aprovar
                                </span>
                                <span title="Cancelar" >
                                    <i class="h4 bi bi-x-circle my-auto text-danger" ></i> Cancelar
                                </span>
                            </div>
                        </div>

                        {!! $orders->onEachSide( 1 )->links( "pagination::bootstrap-5" ) !!}

                        <div class="table-responsive my-3" >
                            <table class="table table-borderless table-striped table-sm mb-0" >
                                <thead>
                                    <tr>
                                        <th class="text-center text-nowrap" >ID</th>
                                        <th class="text-center text-nowrap" >
                                            Mkt ID
                                            <a tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-content="ID do pedido gerado pelo marketplace" >
                                                <i class="bi bi-info-circle text-primary" > </i>
                                            </a>
                                        </th>
                                        <th class="text-center text-nowrap" >Status</th>
                                        <th class="text-center text-nowrap" >Qntdd</th>
                                        <th class="text-center text-nowrap" >Preço máximo</th>
                                        <th class="text-center text-nowrap" >
                                            ID usuário
                                            <a tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-content="ID do usuário ao qual o pedido ficará atrelado no marketplace" >
                                                <i class="bi bi-info-circle text-primary" > </i>
                                            </a>
                                        </th>
                                        <th class="text-center text-nowrap" >
                                            ID produto
                                            <a tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-content="ID do produto no marketplace" >
                                                <i class="bi bi-info-circle text-primary" > </i>
                                            </a>
                                        </th>
                                        <th class="text-center text-nowrap" style="min-width:400px" >Nome produto</th>
                                        <th class="text-center text-nowrap" >
                                            Endereço fatura
                                            <a tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-content="Endereço da empresa" >
                                                <i class="bi bi-info-circle text-primary" > </i>
                                            </a>
                                        </th>
                                        <th class="text-center text-nowrap" >
                                            Endereço entrega
                                            <a tabindex="0" role="button" data-bs-toggle="popover" data-bs-trigger="focus" data-bs-content="Endereço do cliente final" >
                                                <i class="bi bi-info-circle text-primary" > </i>
                                            </a>
                                        </th>
                                        <th class="text-center text-nowrap" >Ações</th>
                                    </tr>
                                </thead>

                                <tbody id="ordersTableBody" >
                                    @forelse( $orders as $order )
                                        <tr>
                                            {{-- {{ $order->status[ "color" ] }} --}}
                                            <td class="text-center" >{{ $order->ord_id }}</td>
                                            <td class="text-center" >
                                                {{ $order->mkt_id }}
                                                @if ( $order->mkt_id )
                                                    <br>
                                                    <a href="{{ route( "mkt.order.fs", [ "order_id" => $order->mkt_id ] ) }}" target="_blank" rel="noopener noreferrer" >
                                                        Ver dados FS
                                                    </a>
                                                @endif
                                            </td>
                                            <td class="text-nowrap text-center" >
                                                <span class="badge rounded-pill mb-0 {{ $order->status[ "color" ] }} ">
                                                    {{ $order->status[ "name" ] }}
                                                </span>
                                            </td>
                                            <td class="text-center"          >{{ $order->product_qntt }}</td>
                                            <td class="text-nowrap text-end" >{{ number_format( $order->max_price, 2, ',', '.' ) }}</td>
                                            <td class="text-center"          >{{ $order->customer_id  }}</td>
                                            <td class="text-center"          >{{ $order->product_id   }}</td>
                                            <td style="min-width:400px" >
                                                <p class="mb-0 name-preview" >{{ Str::limit( $order->product_name, 50 ) }}</p>
                                                <div class="name-full d-none" >
                                                    {{ $order->product_name }}
                                                </div>
                                                <div class="text-center" >
                                                    <button class="btn btn-sm btn-link show-more-name" >Ver mais</button>
                                                </div>
                                            </td>
                                            <td class="text-nowrap" >
                                                <p class="mb-0 address-preview" >{{ Str::limit( implode( " ", $order->billing ), 20 ) }}</p>
                                                <div class="address-full d-none" >
                                                    @foreach ( $order->billing as $key => $value )
                                                        <p class="mb-0" >{{ $value }}</p>
                                                    @endforeach
                                                </div>
                                                <div class="text-center" >
                                                    <button class="btn btn-sm btn-link show-more" >Ver mais</button>
                                                </div>
                                            </td>
                                            <td class="text-nowrap" >
                                                <p class="mb-0 address-preview" >{{ Str::limit( implode( " ", $order->shipping ), 20 ) }}</p>
                                                <div class="address-full d-none" >
                                                    @foreach ( $order->shipping as $key => $value )
                                                        <p class="mb-0" >{{ $value }}</p>
                                                    @endforeach
                                                </div>
                                                <div class="text-center" >
                                                    <button class="btn btn-sm btn-link show-more" >Ver mais</button>
                                                </div>
                                            </td>
                                            <td class="text-center text-nowrap" data-ord_id="{{ $order->ord_id }}" data-file_name="{{ $order->file_name }}" >
                                                @if ( in_array( $order->ord_status, [ "not_processed", "product_not_found", "over_max" ] ) )
                                                    <button class="btn btn-sm btn-block btn-primary text-white p-1 create-order"  title="Criar" >
                                                        <i class="h3 bi bi-plus-circle i-align-center"  ></i>
                                                    </button>
                                                @endif

                                                @if( $order->ord_status == "processed" )
                                                    <button class="btn btn-sm btn-block btn-success text-white p-1 approve-order" title="Aprovar" >
                                                        <i class="h3 bi bi-check-circle i-align-center" ></i>
                                                    </button>
                                                @endif

                                                @if( in_array( $order->ord_status, [ "processed", "approved" ] ) )
                                                    <button class="btn btn-sm btn-block btn-danger text-white p-1 cancel-order"  title="Cancelar" >
                                                        <i class="h3 bi bi-x-circle i-align-center"     ></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="11" class="text-center" >
                                                Nenhum arquivo importado
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {!! $orders->onEachSide( 1 )->links( "pagination::bootstrap-5" ) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-none" >
        <form id="order-form"  method="POST" class="d-none" action="" >
            @csrf
            <input type="hidden" name="ord_id"    >
            <input type="hidden" name="file_name" >
        </form>
    </div>
@endsection

@section( "js" )
    <script type="module" >
        var ppTrgList = [].slice.call( document.querySelectorAll( "[data-bs-toggle='popover']" ) );
        var ppList    = ppTrgList.map( function ( popoverTriggerEl ) { return new bootstrap.Popover( popoverTriggerEl ); } );

        window.setTimeout( function ()
        {
            $( ".dismiss-alert" ).addClass( "d-none" );
        }, 10000 );

        $( "#import-form" ).on( "submit", function()
        {
            $( "#load" ).removeClass( "d-none" );
        } );

        // File filter
        $( "#orders-file" ).on( "change", function( e )
        {
            const file      = $( this ).val();

            if ( !file ) return;

            $( "#load" ).removeClass( "d-none" );

            const parser    = new URL( window.location );
            parser.searchParams.set( "file", file );
            window.location = parser.href;
        } );

        // Status filter
        $( "#orders-status" ).on( "change", function()
        {
            const status    = $( this ).val();

            if ( !status ) return;

            $( "#load" ).removeClass( "d-none" );

            const parser    = new URL( window.location );
            parser.searchParams.set( "status", status );
            window.location = parser.href;
        } );

        // Show more/less for name
        $( document ).on( "click", ".show-more-name", function()
        {
            var row = $( this ).closest( "tr" );

            row.find( ".name-preview" ).toggleClass( "d-none" );
            row.find( ".name-full"    ).toggleClass( "d-none" );

            $( this ).text( function( i, text )
            {
                return text === "Ver mais" ? "Ver menos" : "Ver mais";
            } );

            // Hide other expanded name
            $( "tr" ).not( row ).find( ".name-preview" ).removeClass( "d-none" );
            $( "tr" ).not( row ).find( ".name-full"    ).addClass( "d-none" );
            $( "tr" ).not( row ).find( ".show-more-name"  ).text( "Ver mais" );
        } );

        // Show more/less for addresses
        $( document ).on( "click", ".show-more", function()
        {
            var row = $( this ).closest( "tr" );

            row.find( ".address-preview" ).toggleClass( "d-none" );
            row.find( ".address-full"    ).toggleClass( "d-none" );

            $( this ).text( function( i, text )
            {
                return text === "Ver mais" ? "Ver menos" : "Ver mais";
            } );

            // Hide other expanded addresses
            $( "tr" ).not( row ).find( ".address-preview" ).removeClass( "d-none" );
            $( "tr" ).not( row ).find( ".address-full"    ).addClass( "d-none" );
            $( "tr" ).not( row ).find( ".show-more"       ).text( "Ver mais" );
        } );

        // Hide expanded addresses when clicking outside
        $( document ).on( "click", function( event )
        {
            if ( !$( event.target ).closest( "td" ).length )
            {
                $( ".address-preview" ).removeClass( "d-none" );
                $( ".address-full"    ).addClass( "d-none" );
                $( ".show-more"       ).text( "Exibir mais" );
            }
        } );

        $( ".create-order" ).on( "click", function()
        {
            const ord_id    = $( this ).parent().data( "ord_id"    );
            const file_name = $( this ).parent().data( "file_name" );

            setFormValues( "create", ord_id, file_name );
        } );

        $( ".approve-order" ).on( "click", function()
        {
            const ord_id    = $( this ).parent().data( "ord_id"    );
            const file_name = $( this ).parent().data( "file_name" );

            setFormValues( "approve", ord_id, file_name );
        } );

        $( ".cancel-order" ).on( "click", function()
        {
            const ord_id    = $( this ).parent().data( "ord_id"    );
            const file_name = $( this ).parent().data( "file_name" );

            setFormValues( "cancel", ord_id, file_name );
        } );

        window.setFormValues = function ( action, ord_id, file_name )
        {
            let route   = "";
            let message = "";

            if ( action == "create"  )
            {
                route   = "{{ route( 'mkt.order.create'  ) }}";
                message = `Deseja criar o pedido ID: ${ord_id}?\nO pedido será criado no marketplace e enviado a Fast Shop!`;
            }
            else if ( action == "approve" )
            {
                route   = "{{ route( 'mkt.order.approve' ) }}";
                message = `Deseja aprovar o pedido ID: ${ord_id}?\nO pedido será aprovado para a Fast Shop!`;
            }
            else if ( action == "cancel"  )
            {
                route   = "{{ route( 'mkt.order.cancel'  ) }}";
                message = `Deseja cancelar o pedido ID: ${ord_id}?\nO pedido será cancelado na Fast Shop e no marketplace!`;
            }

            $( "#order-form input[name='ord_id']"    ).val( ord_id    );
            $( "#order-form input[name='file_name']" ).val( file_name );
            $( "#order-form"                         ).attr( "action", route );

            if ( confirm( message ) )
            {
                $( "#load" ).removeClass( "d-none" );
                $( "#order-form" ).submit();
            }
        }
    </script>
@endsection
