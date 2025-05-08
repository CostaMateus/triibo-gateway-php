@if ( Session::has( "error" ) )
    <div class="row dismiss-alert" >
        <div class="col-12 col-md-6 mx-auto" >
            <div class="alert alert-danger" >
                <p class="mb-0" >{{ Session::get( "error" ) }}</p>
            </div>
        </div>
    </div>
@endif

@if ( Session::has( "success" ) )
    <div class="row dismiss-alert" >
        <div class="col-12 col-md-6 mx-auto" >
            <div class="alert alert-success" >
                <p class="mb-0" >{{ Session::get( "success" ) }}</p>
            </div>
        </div>
    </div>
@endif

@if ( Session::has( "warning" ) )
    <div class="row dismiss-alert" >
        <div class="col-12 col-md-6 mx-auto" >
            <div class="alert alert-warning" >
                <p class="mb-0" >{{ Session::get( "warning" ) }}</p>
            </div>
        </div>
    </div>
@endif
