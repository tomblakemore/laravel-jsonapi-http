<?php

namespace JsonApiHttp\Middleware;

use JsonApiHttp\Request;

class CheckAcceptHeaders
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
        if (($accept = $request->header('Accept'))) {

            if (!is_array($accept)) {
                $accept = array($accept);
            }

            $count = $invalid = 0;

            foreach ($accept as $value) {

                if (!str_contains($value, 'json')) {
                    continue;
                }

                $count++;

                if (!ends_with($value, 'json')) {
                    $invalid++;
                }
            }

            if ($count === 0 || $count > 0 && $count === $invalid) {
                return response(null, 406);
            }
        }

        return $next($request);
    }
}
