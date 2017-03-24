<?php

namespace JsonApiHttp\Middleware;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use JsonApiHttp\Request;

class VerifyRelationship
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
        if (!($model = $request->model())) {
            return response(null, 422);
        }

        $relation = $request->route()->parameter('relation');

        if (!in_array($relation, $model->relations())) {
            return response(null, 404);
        }

        $method = snake_case(camel_case($relation));

        if (!method_exists($model, $method)) {
            return response(null, 404);
        }

        if (in_array($request->method(), ['PATCH', 'POST', 'PUT'])) {

            if (!in_array($relation, $model->getFillableRelations())) {
                return response(null, 422);
            }

            if (!($relationship = $request->relationship())) {
                return response(null, 422);
            }

            $belongsTo = ($model->{$method}() instanceof BelongsTo);

            if ($belongsTo && $relationship->hasMany()) {
                return response(null, 422);
            } elseif (!$relationship->hasMany() && !$belongsTo) {
                return response(null, 422);
            }

            if ($relation === 'parent') {

                $type = $relationship->relation()->type();

                if ($type !== $model->type()) {
                    return response(null, 422);
                }
            }
        }

        return $next($request);
    }
}
