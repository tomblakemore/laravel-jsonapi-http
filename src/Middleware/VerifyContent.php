<?php

namespace JsonApiHttp\Middleware;

use JsonApiHttp\Request;

class VerifyContent
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
        if (in_array($request->method(), ['PATCH', 'POST', 'PUT'])) {

            if (($contentType = $request->header('Content-Type'))) {

                if (str_contains($contentType, 'json')) {

                    if (!($content = $request->getContent())) {
                        return response(null, 400);
                    }

                    if (!($json = json_decode($content, true))) {
                        return response(null, 400);
                    }
                }
            }
        }

        return $next($request);
    }
}
