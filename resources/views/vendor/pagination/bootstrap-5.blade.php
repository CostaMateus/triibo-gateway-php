<nav>
    <div class="row d-sm-none" >
        <div class="col-12 text-center mb-3" >
            <p class="small text-muted mb-0" >
                {!! __( key: "Showing" ) !!}
                <span class="fw-semibold" >{{ $paginator->firstItem() }}</span>
                {!! __( key: "to"      ) !!}
                <span class="fw-semibold" >{{ $paginator->lastItem()  }}</span>
                {!! __( key: "of"      ) !!}
                <span class="fw-semibold" >{{ $paginator->total()     }}</span>
                {!! __( key: "results" ) !!}
            </p>
        </div>

        <div class="col-12 d-flex justify-content-center" >
            <ul class="pagination mb-0" >
                {{-- Previous Page Link --}}
                @if ( $paginator->onFirstPage() )
                    <li class="page-item disabled" aria-disabled="true" >
                        <span class="page-link" >
                            @lang( key: "pagination.previous" )
                        </span>
                    </li>
                @else
                    <li class="page-item" >
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" >
                            @lang( key: "pagination.previous" )
                        </a>
                    </li>
                @endif

                {{-- Next Page Link --}}
                @if ( $paginator->hasMorePages() )
                    <li class="page-item" >
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" >
                            @lang( key: "pagination.next" )
                        </a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" >
                        <span class="page-link" >
                            @lang( key: "pagination.next" )
                        </span>
                    </li>
                @endif
            </ul>
        </div>
    </div>

    <div class="d-none flex-sm-fill d-sm-flex align-items-sm-center justify-content-sm-between" >
        <p class="small text-muted mb-0" >
            {!! __( key: "Showing" ) !!}
            <span class="fw-semibold" >{{ $paginator->firstItem() }}</span>
            {!! __( key: "to"      ) !!}
            <span class="fw-semibold" >{{ $paginator->lastItem()  }}</span>
            {!! __( key: "of"      ) !!}
            <span class="fw-semibold" >{{ $paginator->total()     }}</span>
            {!! __( key: "results" ) !!}
        </p>

        @if ( $paginator->hasPages() )
            <ul class="pagination pagination-sm mb-0 text-iph-light" >
                {{-- Previous Page Link --}}
                @if ( $paginator->onFirstPage() )
                    <li class="page-item disabled" aria-disabled="true" aria-label="@lang ( key: "pagination.previous" )" >
                        <span class="page-link" aria-hidden="true" >
                            &lsaquo;
                        </span>
                    </li>
                @else
                    <li class="page-item" >
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang ( key: "pagination.previous" )" >
                            &lsaquo;
                        </a>
                    </li>
                @endif

                {{-- Pagination Elements --}}
                @foreach ( $elements as $element )
                    {{-- "Three Dots" Separator --}}
                    @if ( is_string( value: $element ) )
                        <li class="page-item disabled" aria-disabled="true" >
                            <span class="page-link" >
                                {{ $element }}
                            </span>
                        </li>
                    @endif

                    {{-- Array Of Links --}}
                    @if ( is_array( value: $element ) )
                        @foreach ( $element as $page => $url )
                            @if ( $page == $paginator->currentPage() )
                                <li class="page-item active" aria-current="page" >
                                    <span class="page-link" >
                                        {{ $page }}
                                    </span>
                                </li>
                            @else
                                <li class="page-item" >
                                    <a class="page-link" href="{{ $url }}" >
                                        {{ $page }}
                                    </a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                {{-- Next Page Link --}}
                @if ( $paginator->hasMorePages() )
                    <li class="page-item" >
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang ( key: "pagination.next" )" >
                            &rsaquo;
                        </a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true" aria-label="@lang ( key: "pagination.next" )" >
                        <span class="page-link" aria-hidden="true" >
                            &rsaquo;
                        </span>
                    </li>
                @endif
            </ul>
        @endif
    </div>
</nav>
