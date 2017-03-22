<?php

namespace App\Http\Middleware;

use JsonApiHttp\Request;

class AddResourceAttributesToModel
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
        if (($model = $request->model()) && ($request->resource())) {
            $model->fill($request->resource()->attributes()->toDbArray());
        }

        return $next($request);
    }
}
