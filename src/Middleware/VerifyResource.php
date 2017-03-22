<?php

namespace App\Http\Middleware;

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
                abort(422, 'Missing model'); // Unprocessable Entity
            }

            if (!($resource = $request->resource())) {
                abort(422, 'Missing resource'); // Unprocessable Entity
            }

            if ($resource->type() !== $request->type()) {
                abort(422, 'Unmatched resource type'); // Unprocessable Entity
            }

            $attributes = $resource->attributes()->toArray();

            if (!empty($attributes) && !Arr::isAssoc($attributes)) {
                abort(400, 'Invalid attributes'); // Bad Request
            }

            if ($resource->type() !== $model->type()) {
                abort(422, 'Unmatched resource type'); // Unprocessable Entity
            }

            if ($model->exists) {

                if ($resource->id() !== $model->getRouteKey()) {
                    abort(404, 'Unmatched resource'); // Not Found
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
                    abort(422, 'Unmatched relation'); // Unprocessable Entity
                }

                $belongsTo = ($model->{$method}() instanceof BelongsTo);

                if ($belongsTo && $relationship->hasMany()) {
                    abort(422, 'Unmatched relationship type'); // Unprocessable Entity
                } elseif (!$relationship->hasMany() && !$belongsTo) {
                    abort(422, 'Unmatched relationship type'); // Unprocessable Entity
                }

                if ($name === 'parent') {

                    $parent = $relationships->get('parent');

                    if (($relation = $parent->relation())) {

                        if ($relation->type() !== $resource->type()) {
                            abort(422, 'Invalid parent type'); // Unprocessable Entity
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
