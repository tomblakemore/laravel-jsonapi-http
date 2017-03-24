<?php

namespace JsonApiHttp\Middleware;

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
                return response(null, 400);
            }
        }

        return $next($request);
    }
}
