<?php

namespace JsonApiHttp\Middleware;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use JsonApiHttp\Request;

class AddRelationshipToModel
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

            $relations = $model->{$request->route()->parameter('relation')}();

            $collection = new Collection;

            foreach ($request->relationship()->relations() as $relation) {

                $repository = \App::make(str_plural($relation->type()));

                if (!($related = $repository->get($relation->id()))) {
                    abort(422, 'Unmatched resource type'); // Unprocessable Entity
                }

                if ($related->instance_id !== $model->instance_id) {
                    abort(404, 'Resource not found'); // Not Found
                }

                if ($relations instanceof BelongsTo) {
                    $relations->associate($related);
                } else {
                    $related->timestamps = false; // Temporarily disable updating the timestamps when saving has many relations
                    $collection->push($related);
                }
            }

            \DB::beginTransaction();

            if ($relations instanceof BelongsToMany) {
                $relations->sync($collection);
            }

            if ($relations instanceof HasMany) {
                $relations->whereNotIn('id', $collection->modelKeys());
                $relations->update([$relations->getForeignKey() => null]);
                $relations->saveMany($collection);
            }

            $response = $next($request);

            if ($response->isSuccessful()) {
                \DB::commit();
            } else {
                \DB::rollback();
            }

            return $response;
        }

        return $next($request);
    }
}
