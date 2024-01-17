@extends( "layouts.app" )

@section( "content"     )
<div class="container" >
    <div class="row justify-content-center" >

        <div class="col-md-8" >
            <h2 class="text-left" >Documentação</h2>
        </div>
        <div class="col-md-8" >

            <div class="accordion accordion-flush" id="accordionTriibo" >

                @include( "doc_parts.chatguru" )
                @include( "doc_parts.awin"     )
                @include( "doc_parts.cofry"    )
                @include( "doc_parts.fastshop" )
                @include( "doc_parts.mautic"   )

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
@endsection
