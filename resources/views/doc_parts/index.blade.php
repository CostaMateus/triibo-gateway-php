@extends( view: "layouts.app" )

@section( section: "title", content: "Documentação" )

@section( section: "content" )
    <div class="app-content" >
        <div class="container-fluid" >
            <div class="row" >

                <div class="col-12 col-sm-10 col-md-8 col-lg-6 mx-auto" >
                    <h2 class="text-left" >Documentação</h2>
                    <div class="accordion accordion-flush" id="accordionTriibo" >
                        @include( view: "doc_parts.chatguru"                   )
                        @include( view: "doc_parts.awin"                       )
                        @include( view: "doc_parts.cofry"                      )
                        @include( view: "doc_parts.fastshop"                   )
                        @include( view: "doc_parts.mautic"                     )
                        @include( view: "doc_parts.plugin-triibo-api-services" )
                        @include( view: "doc_parts.plugin-triibo-assinaturas"  )
                        @include( view: "doc_parts.plugin-triibo-auth"         )
                        @include( view: "doc_parts.plugin-triibo-eventos"      )
                        @include( view: "doc_parts.plugin-triibo-fastshop"     )
                        @include( view: "doc_parts.plugin-triibo-payments"     )
                    </div>

                </div>

            </div>
        </div>
    </div>
@endsection
