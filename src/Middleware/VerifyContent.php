<?php

namespace App\Http\Middleware;

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
                        abort(400, 'No content'); // Bad Request
                    }

                    if (!($json = json_decode($content, true))) {
                        abort(400, 'Not valid JSON content'); // Bad Request
                    }
                }
            }
        }

        return $next($request);
    }
}
