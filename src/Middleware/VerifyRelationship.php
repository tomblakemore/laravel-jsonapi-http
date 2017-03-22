<?php

namespace App\Http\Middleware;

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
            abort(422, 'Unmatched resource type'); // Unprocessable Entity
        }

        $relation = $request->route()->parameter('relation');

        if (!in_array($relation, $model->relations())) {
            abort(404, 'Invalid relation'); // Not Found
        }

        $method = snake_case(camel_case($relation));

        if (!method_exists($model, $method)) {
            abort(404, 'Relation not found'); // Not Found
        }

        if (in_array($request->method(), ['PATCH', 'POST', 'PUT'])) {

            if (!in_array($relation, $model->getFillableRelations())) {
                abort(422, 'Invalid relation'); // Unprocessable Entity
            }

            if (!($relationship = $request->relationship())) {
                abort(422, 'Missing relationship'); // Unprocessable Entity
            }

            $belongsTo = ($model->{$method}() instanceof BelongsTo);

            if ($belongsTo && $relationship->hasMany()) {
                abort(422, 'Unmatched relationship type'); // Unprocessable Entity
            } elseif (!$relationship->hasMany() && !$belongsTo) {
                abort(422, 'Unmatched relationship type'); // Unprocessable Entity
            }

            if ($relation === 'parent') {

                $type = $relationship->relation()->type();

                if ($type !== $model->type()) {
                    abort(422, 'Invalid parent type'); // Unprocessable Entity
                }
            }
        }

        return $next($request);
    }
}
