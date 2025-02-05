import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import $ from 'jquery';
window.$ = $;
window.jQuery = $;
window.jquery = $;

import Tablesort from 'tablesort';
window.Tablesort = Tablesort;

import 'jquery-mask-plugin';
import 'overlayscrollbars';

jquery( function ()
{
    import ( "tablesort/src/sorts/tablesort.number" );
    import ( "tablesort/src/sorts/tablesort.date"   );
} );

let token = document.head.querySelector( 'meta[name="csrf-token"]' );

if ( token )
    window.axios.defaults.headers.common[ 'X-CSRF-TOKEN' ] = token.content;
else
    console.error('CSRF token not found: https://laravel.com/docs/csrf#csrf-x-csrf-token');
