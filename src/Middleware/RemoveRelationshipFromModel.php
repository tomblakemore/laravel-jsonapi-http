<?php

namespace App\Http\Middleware;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JsonApiHttp\Request;

class RemoveRelationshipFromModel
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
        if (($model = $request->model()) && $request->relationship()) {

            if ($request->relationship()->relations()->isEmpty()) {

                $name = $request->route()->parameter('relation');

                if (($relation = $model->{$name}()) instanceof BelongsTo) {
                    $relation->dissociate();
                }

                /*
                |---------------------------------------------------------------
                | Note: BelongsToMany/HasMany
                |---------------------------------------------------------------
                |
                | BelongsToMany and HasMany relationships are sorted in the
                | AddRelationshipToModel middleware.
                |
                */
            }
        }

        return $next($request);
    }
}
