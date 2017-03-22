<?php

namespace App\Http\Middleware;

use JsonApiHttp\Request;

class CheckForContentTypeHeader
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
        if ($request->getContent()) {

            if (!($contentType = $request->header('Content-Type'))) {
                abort(400, "Missing 'Content-Type' header"); // Bad Request
            }
        }

        return $next($request);
    }
}
