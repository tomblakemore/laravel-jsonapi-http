<?php

namespace JsonApiHttp\Middleware;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use JsonApiHttp\Request;

class VerifyResource
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

            if (!($model = $request->model())) {
                return response(null, 422);
            }

            if (!($resource = $request->resource())) {
                return response(null, 422);
            }

            if ($resource->type() !== $request->type()) {
                return response(null, 422);
            }

            $attributes = $resource->attributes()->toArray();

            if (!empty($attributes) && !Arr::isAssoc($attributes)) {
                return response(null, 400);
            }

            if ($resource->type() !== $model->type()) {
                return response(null, 422);
            }

            if ($model->exists) {

                if ($resource->id() !== $model->getRouteKey()) {
                    return response(null, 404);
                }
            }

            $relationships = $resource->relationships();

            foreach ($relationships as $name => $relationship) {

                if (!in_array($name, $model->getFillableRelations())) {
                    $relationships->forget($name);
                    continue;
                }

                $method = snake_case(camel_case($name));

                if (!method_exists($model, $method)) {
                    return response(null, 422);
                }

                $belongsTo = ($model->{$method}() instanceof BelongsTo);

                if ($belongsTo && $relationship->hasMany()) {
                    return response(null, 422);
                } elseif (!$relationship->hasMany() && !$belongsTo) {
                    return response(null, 422);
                }

                if ($name === 'parent') {

                    $parent = $relationships->get('parent');

                    if (($relation = $parent->relation())) {

                        if ($relation->type() !== $resource->type()) {
                            return response(null, 422);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
