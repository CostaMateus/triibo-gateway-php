@extends( "layouts.app" )

@section( "title", "Documentação" )

@section( "content" )
    <div class="app-content" >
        <div class="container-fluid" >
            <div class="row" >

                <div class="col-12 col-sm-10 col-md-8 col-lg-6 mx-auto" >
                    <h2 class="text-left" >Documentação</h2>
                    <div class="accordion accordion-flush" id="accordionTriibo" >
                        @include( "doc_parts.chatguru"                   )
                        @include( "doc_parts.awin"                       )
                        @include( "doc_parts.cofry"                      )
                        @include( "doc_parts.fastshop"                   )
                        @include( "doc_parts.mautic"                     )
                        @include( "doc_parts.plugin-triibo-api-services" )
                        @include( "doc_parts.plugin-triibo-assinaturas"  )
                        @include( "doc_parts.plugin-triibo-auth"         )
                        @include( "doc_parts.plugin-triibo-eventos"      )
                        @include( "doc_parts.plugin-triibo-fastshop"     )
                        @include( "doc_parts.plugin-triibo-payments"     )
                    </div>

                </div>

            </div>
        </div>
    </div>
@endsection
