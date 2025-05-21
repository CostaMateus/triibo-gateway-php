@if ( Session::has( key: "error" ) )
    <div class="row dismiss-alert" >
        <div class="col-12 col-md-6 mx-auto" >
            <div class="alert alert-danger" >
                <p class="mb-0" >{{ Session::get( key: "error" ) }}</p>
            </div>
        </div>
    </div>
@endif

@if ( Session::has( key: "success" ) )
    <div class="row dismiss-alert" >
        <div class="col-12 col-md-6 mx-auto" >
            <div class="alert alert-success" >
                <p class="mb-0" >{{ Session::get( key: "success" ) }}</p>
            </div>
        </div>
    </div>
@endif

@if ( Session::has( key: "warning" ) )
    <div class="row dismiss-alert" >
        <div class="col-12 col-md-6 mx-auto" >
            <div class="alert alert-warning" >
                <p class="mb-0" >{{ Session::get( key: "warning" ) }}</p>
            </div>
        </div>
    </div>
@endif
