<?php

namespace App\Http\Middleware;

use JsonApiHttp\Payload;
use JsonApiHttp\Request;

class SetResponseHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param \JsonApiHttp\Request $request
     * @param \Closure $next
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, \Closure $next)
    {
        $response = $next($request);

        if (!empty($response->getContent())) {

            // Servers MUST send all JSON API data in response documents with 
            // the header Content-Type: application/vnd.api+json without any 
            // media type parameters.
            $response->header('Content-Type', 'application/vnd.api+json');
        }

        return $response;
    }
}
