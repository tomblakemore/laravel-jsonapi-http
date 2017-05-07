<?php

namespace JsonApiHttp;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;

use IteratorAggregate;

use JsonApiHttp\Contracts\Model;
use JsonApiHttp\Exceptions\ControllerException;
use JsonApiHttp\Relationships\BelongsTo as BelongsToRelationship;
use JsonApiHttp\Relationships\HasMany as HasManyRelationship;

class Controller extends BaseController
{
    /**
     * The resource type.
     *
     * @var string
     */
    protected $type = '';

    /**
     * Add a resource to the payload.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Payload $payload
     * @param \JsonApiHttp\Contracts\Model $model
     * @return void
     */
    protected function addResource(
        Request $request,
        Payload $payload,
        Model $model
    )
    {
        $id = $model->getRouteKey();

        if (!($resource = $payload->resources()->get($id))) {
            $payload->resources()->push(($resource = new Resource($model)));
        }

        $resource->links()->put('self',
            route("{$resource->type()}.show", [
                'id' => $resource->id()
            ],
            false
        ));

        $includes = $request->includes();

        $this->addRelationships($payload, $resource, $model, $includes);
    }

    /**
     * Add a collection of resources to the payload.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Payload $payload
     * @param \IteratorAggregate $items
     * @return void
     */
    protected function addResources(
        Request $request,
        Payload $payload,
        IteratorAggregate $items
    )
    {
        foreach ($items as $model) {
            $this->addResource($request, $payload, $model);
        }
    }

    /**
     * Populate a payload and resource with relationship information.
     *
     * @access protected
     * @param \JsonApiHttp\Payload $payload
     * @param \JsonApiHttp\Resource $resource
     * @param \JsonApiHttp\Contracts\Model $model
     * @param array $includes
     * @return void
     */
    protected function addRelationships(
        Payload $payload,
        Resource $resource,
        Model $model,
        array $includes = []
    )
    {
        foreach ($model->relations() as $relation) {

            $related = $model->{snake_case(camel_case($relation))}();

            if (!($relationship = $resource->relationships()->get($relation))) {

                if ($related instanceof BelongsTo) {
                    $relationship = new BelongsToRelationship;
                } else {
                    $relationship = new HasManyRelationship;
                    $relationship->hidePagination();
                }
            }

            if (array_key_exists($relation, $includes)) {

                $relationship->showRelationData();

                if ($relationship->hasMany()) {
                    $items = $related->get();
                } else {
                    $items = new Collection;
                    if (($item = $related->first())) {
                        $items->push($item);
                    }
                }

                foreach ($items as $item) {

                    $id = $item->getRouteKey();

                    if (!($included = $payload->included()->get($id))) {

                        $included = new Resource($item);

                        $included->links()->put('self', route(
                            "{$item->type()}.show", [
                                'id' => $id
                            ],
                            false
                        ));

                        $payload->included()->push($included);
                    }

                    if (request()->query('include') !== '*') {

                        $this->addRelationships(
                            $payload,
                            $included,
                            $item,
                            $includes[$relation]
                        );
                    }

                    $relationship->relations()->push(new Relation($item));
                }
            }

            $relationship->links()->put('related',
                route("{$resource->type()}.relations.index", [
                    'id' => $resource->id(),
                    'relation' => $relation
                ],
                false
            ));

            $relationship->links()->put('self', 
                route("{$resource->type()}.relations.show", [
                    'id' => $resource->id(),
                    'relation' => $relation
                ],
                false
            ));

            $resource->relationships()->put(
                $relation,
                $relationship
            );
        }
    }

    /**
     * Build and return an error response.
     *
     * @access protected
     * @param \JsonApiHttp\Contracts\Model $model
     * @param bool $rollback
     * @return \Illuminate\Http\Response
     */
    protected function errorResponse(Model $model, $rollback = true)
    {
        return $this->errorResponseFromMessageBag($model->errors(), $rollback);
    }

    /**
     * Build and return an error response from a message bag.
     *
     * @access protected
     * @param \Illuminate\Support\MessageBag $errors
     * @param bool $rollback
     * @return \Illuminate\Http\Response
     */
    protected function errorResponseFromMessageBag(
        MessageBag $errors,
        $rollback = true
    )
    {
        if ($rollback) {
            \DB::rollback();
        }

        $payload = new Payload;

        foreach ($errors->messages() as $name => $messages) {
            foreach ($messages as $message) {
                $payload->errors()->push(
                    new Error([
                        'detail' => $message,
                        'source' => [
                            'pointer' => "data/attributes/{$name}"
                        ]
                    ])
                );
            }
        }

        $response = new Response;

        $response->setContent($payload);
        $response->setStatusCode(422);

        return $response;
    }

    /**
     * Build a generic resource/resources payload.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param mixed $items
     * @return \JsonApiHttp\Payload
     */
    protected function payload(Request $request, $items)
    {
        if (($model = $items) instanceof Model) {
            return $this->resource($request, $model);
        }

        return $this->resources($request, $items);
    }

    /**
     * Build a resource payload.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Contracts\Model $model
     * @return \JsonApiHttp\Payload
     */
    protected function resource(Request $request, Model $model)
    {
        $payload = new Payload;

        $this->addResource($request, $payload, $model);

        $payload->links()->put('self',
            route("{$model->type()}.show", [
                'id' => $model->getRouteKey()
            ],
            false
        ));

        return $payload;
    }

    /**
     * Build a resources payload.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param \Illuminate\Pagination\LengthAwarePaginator $items
     * @return \JsonApiHttp\Payload
     */
    protected function resources(Request $request, LengthAwarePaginator $items)
    {
        $payload = (new PayloadCollection)->setPaginator($items);

        $this->addResources($request, $payload, $items);

        $payload->paginator()->setPath(
            route("{$this->type()}.index", [], false)
        );

        return $payload;
    }

    /**
     * Get type of resource associated with the class using this trait.
     *
     * @access protected
     * @return string
     * @throws \JsonApiHttp\Exceptions\ControllerException
     */
    protected function type()
    {
        if (!$this->type) {
            throw new ControllerException('Type missing');
        }

        return $this->type;
    }
}
