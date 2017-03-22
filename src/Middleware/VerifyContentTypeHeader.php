<?php

namespace App\Http\Middleware;

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
                abort(406, "Too many 'Content-Type' headers"); // Not Acceptable
            }

            if (!starts_with($contentType, 'application/vnd.api+json')) {
                abort(406, "Unsupported 'Content-Type' header"); // Not Acceptable
            }

            if (!ends_with($contentType, 'application/vnd.api+json')) {
                abort(415, "Unsupported media type in 'Content-Type' header"); // Unsupported Media Type
            }
        }

        return $next($request);
    }
}
