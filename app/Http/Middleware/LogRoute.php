<?php

namespace App\Http\Middleware;

use DB;
use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class LogRoute
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle( Request $request, Closure $next )
    {
        $response = $next( $request );

        if ( defined( "LARAVEL_START" ) and $response instanceof Response )
            $response->headers->add( [
                "X-RESPONSE-TIME" => microtime( true ) - LARAVEL_START
            ] );

        return $response;
    }

    public function terminate( Request $request, JsonResponse $response )
    {
        if ( defined( "LARAVEL_START" ) and $request instanceof Request )
            $this->log( $request, $response );
    }

    protected function log( Request $request, JsonResponse $response )
    {
        $duration = microtime( true ) - LARAVEL_START;
        $url      = $request->fullUrl();
        $method   = $request->getMethod();
        $ip       = $request->getClientIp();
        $status   = $response->getStatusCode();

        // $log = "\nIP: {$ip}\nSTATUS: [{$status}]\nMETHOD: {$method}\nURL: {$url}\nDURATION: {$duration}s";
        // Log::info( $log );

        DB::table( "log_route" )->insert( [
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
