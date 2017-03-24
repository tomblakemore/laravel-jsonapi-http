<?php

namespace JsonApiHttp\Middleware;

use Illuminate\Http\Request as HttpRequest;

use JsonApiHttp\Request;

class SetRequest
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return \Illuminate\Http\Response
     */
    public function handle(HttpRequest $request, \Closure $next)
    {
        $router = $request->getRouteResolver();
        $request = app()->make(Request::class)->setRouteResolver($router);

        return $next($request);
    }
}
