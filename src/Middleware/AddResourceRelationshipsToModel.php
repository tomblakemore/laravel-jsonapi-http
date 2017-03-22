<?php

namespace App\Http\Middleware;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use JsonApiHttp\Request;

class AddResourceRelationshipsToModel
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

            $collections = [];

            $relationships = $request->resource()->relationships();

            foreach ($relationships as $name => $relationship) {

                $relations = $model->{snake_case(camel_case($name))}();

                if (!($relations instanceof BelongsTo)) {
                    $collections[$name] = new Collection;
                }

                foreach ($relationship->relations() as $relation) {

                    if (!($id = $relation->id())) {
                        continue;
                    }

                    $repository = \App::make(str_plural($relation->type()));

                    if (!($related = $repository->get($id))) {
                        abort(422, 'Unmatched resource type'); // Unprocessable Entity
                    }

                    if ($related->instance_id !== $model->instance_id) {
                        abort(404, 'Resource not found'); // Not Found
                    }

                    if ($relations instanceof BelongsTo) {
                        $relations->associate($related);
                    } else {
                        $related->timestamps = false; // Temporarily disable updating the timestamps when saving has many relations
                        $collections[$name]->push($related);
                    }
                }
            }

            if ($model->exists) {

                \DB::beginTransaction();

                foreach ($collections as $name => $collection) {

                    $relations = $model->{$name}();

                    if ($relations instanceof BelongsToMany) {
                        $relations->sync($collection);
                    }

                    if ($relations instanceof HasMany) {

                        $relations->whereNotIn('id', $collection->modelKeys());
                        $relations->update([
                            $relations->getForeignKey() => null
                        ]);

                        $relations->saveMany($collection);
                    }
                }

                $response = $next($request);

                if ($response->isSuccessful()) {
                    \DB::commit();
                } else {
                    \DB::rollback();
                }

                return $response;
            }

            $response = $next($request);

            if ($response->isSuccessful()) {

                foreach ($collections as $name => $collection) {

                    $relations = $model->{$name}();

                    if ($relations instanceof BelongsToMany) {
                        $relations->sync($collection);
                    }

                    if ($relations instanceof HasMany) {

                        $relations->whereNotIn('id', $collection->modelKeys());
                        $relations->update([
                            $relations->getForeignKey() => null
                        ]);

                        $relations->saveMany($collection);
                    }
                }
            }

            return $response;
        }

        return $next($request);
    }
}
