<?php

namespace JsonApiHttp\Middleware;

use JsonApiHttp\Request;

class VerifyContentTypeHeader
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

            $contentType = $request->header('Content-Type');

            if (is_array($contentType)) {
                return response(null, 406);
            }

            if (!starts_with($contentType, 'application/vnd.api+json')) {
                return response(null, 406);
            }

            if (!ends_with($contentType, 'application/vnd.api+json')) {
                return response(null, 406);
            }
        }

        return $next($request);
    }
}
