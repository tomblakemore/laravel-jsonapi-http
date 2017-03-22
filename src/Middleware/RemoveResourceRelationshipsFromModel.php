<?php

namespace App\Http\Middleware;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JsonApiHttp\Request;

class RemoveResourceRelationshipsFromModel
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
        if (($model = $request->model()) && $request->resource()) {

            $relationships = $request->resource()->relationships();

            foreach ($relationships as $name => $relationship) {

                if (!$relationship->relations()->isEmpty()) {
                    continue;
                }

                if (($relations = $model->{$name}()) instanceof BelongsTo) {
                    $relations->dissociate();
                }

                /*
                |---------------------------------------------------------------
                | Note: BelongsToMany/HasMany
                |---------------------------------------------------------------
                |
                | BelongsToMany and HasMany relationships are sorted in the
                | AddResourceRelationshipsToModel middleware.
                |
                */
            }
        }

        return $next($request);
    }
}
