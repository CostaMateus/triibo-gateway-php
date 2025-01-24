<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class LogRoute
{
    /**
     * Handle an incoming request.
     *
     * @param   Request     $request
     * @param   Closure     $next
     * @return  Response|RedirectResponse
     */
    public function handle( Request $request, Closure $next ) : mixed
    {
        $response = $next( $request );

        if ( defined( "LARAVEL_START" ) and $response instanceof Response )
            $response->headers->add( headers: [ "X-RESPONSE-TIME" => microtime( true ) - LARAVEL_START ] );

        return $response;
    }

    /**
     * Undocumented function
     *
     * @param   Request                 $request
     * @param   Response|JsonResponse   $response
     * @return  void
     */
    public function terminate( Request $request, Response|JsonResponse $response ) : void
    {
        if ( defined( "LARAVEL_START" ) and $request instanceof Request )
            $this->log( request: $request, response: $response );
    }

    /**
     * Undocumented function
     *
     * @param   Request                 $request
     * @param   Response|JsonResponse   $response
     * @return  void
     */
    protected function log( Request $request, Response|JsonResponse $response ) : void
    {
        $duration = microtime( true ) - LARAVEL_START;
        $url      = $request->fullUrl();
        $method   = $request->getMethod();
        $ip       = $request->getClientIp();
        $status   = $response->getStatusCode();

        DB::table( table: "log_route" )->insert( values: [
            "duration"   => $duration,
            "status"     => $status,
            "method"     => $method,
            "ip"         => $ip,
            "url"        => $url,
            "created_at" => Carbon::now(),
            "updated_at" => Carbon::now(),
        ] );
    }
}
