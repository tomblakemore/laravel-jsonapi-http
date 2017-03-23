<?php

namespace JsonApiHttp\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\MessageBag;

use IteratorAggregate;

use JsonApiHttp\Error;
use JsonApiHttp\Exceptions\JsonApiHttpException;
use JsonApiHttp\Model;
use JsonApiHttp\Payload;
use JsonApiHttp\PayloadCollection;
use JsonApiHttp\Relation;
use JsonApiHttp\Relationships;
use JsonApiHttp\Relationships\BelongsTo as BelongsToRelationship;
use JsonApiHttp\Relationships\HasMany as HasManyRelationship;
use JsonApiHttp\Request;
use JsonApiHttp\Resource;

trait JsonApiResourceResponses
{
    /**
     * The type of JsonApi resource.
     *
     * @var string
     */
    public $type = '';

    /**
     * Add a resource to the payload.
     *
     * @access protected
     * @param \JsonApiHttp\Payload $payload
     * @param \JsonApiHttp\Model $model
     * @return void
     */
    protected function addResource(Payload $payload, Model $model)
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

        $includes = request()->includes();

        $this->addRelationships($payload, $resource, $model, $includes);
    }

    /**
     * Add a collection of resources to the payload.
     *
     * @access protected
     * @param \JsonApiHttp\Payload $payload
     * @param \IteratorAggregate $items
     * @return void
     */
    protected function addResources(
        Payload $payload,
        IteratorAggregate $items
    )
    {
        foreach ($items as $model) {
            $this->addResource($payload, $model);
        }
    }

    /**
     * Populate a payload and resource with relationship information.
     *
     * @access protected
     * @param \JsonApiHttp\Payload $payload
     * @param \JsonApiHttp\Resource $resource
     * @param \JsonApiHttp\Model $model
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

            if ($related instanceof BelongsTo) {
                $relationship = new BelongsToRelationship;
            } else {
                $relationship = new HasManyRelationship;
                $relationship->hidePagination();
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

                    $included = new Resource($item);

                    $included->links()->put('self', route(
                        "{$item->type()}.show", [
                            'id' => $item->getRouteKey()
                        ],
                        false
                    ));

                    $payload->included()->push($included);

                    $relationship->relations()->push(new Relation($item));

                    if (request()->query('include') !== '*') {

                        $this->addRelationships(
                            $payload,
                            $included,
                            $item,
                            $includes[$relation]
                        );
                    }
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

            $resource->relationships()->add(
                $relation,
                $relationship
            );
        }
    }

    /**
     * Build and return an error response.
     *
     * @access protected
     * @param \JsonApiHttp\Model $model
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
        $response->setStatusCode(422); // Unprocessable Entity

        return $response;
    }

    /**
     * Display a listing of the resource.
     *
     * @param \JsonApiHttp\Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $response = new Response;

        if (!($items = $this->items($request))) {
            return $response->setStatusCode(404); // Not Found
        }

        $payload = $this->payload($request, $items);

        $response->setContent($payload);
        $response->setStatusCode(200); // OK

        return $response;
    }

    /**
     * Get paginated items for a request passing an optional starting query.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param mixed $query
     * @return \Illuminate\Pagination\LengthAwarePaginator
     * @throws \JsonApiHttp\Exceptions\JsonApiHttpException
     */
    protected function items(Request $request, $query = null)
    {
        throw new JsonApiHttpException('Method implementation missing');
    }

    /**
     * Build a payload object for the "cru" responses.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param mixed $items
     * @return \JsonApiHttp\Payload
     */
    protected function payload(Request $request, $items)
    {
        if (($model = $items) instanceof Model) {

            $payload = new Payload;

            $payload->links()->put('self',
                route("{$model->type()}.show", [
                    'id' => $model->getRouteKey()
                ],
                false
            ));

            $items = (new Collection)->push($model);
        }

        else {

            $payload = new PayloadCollection;

            if ($items instanceof LengthAwarePaginator) {
                $payload->setPaginator($items);
            }

            $payload->paginator()->setPath(
                route("{$this->type()}.index", [], false
            ));
        }

        $this->addResources($payload, $items);

        return $payload;
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Http\Requests\JsonApiRequest $request
     * @param \App\Model $model
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Model $model)
    {
        $response = new Response;

        if (method_exists($this, 'payload')) {
            $response->setContent($this->payload($request, $model));
        }

        $response->setStatusCode(200); // OK

        return $response;
    }

    /**
     * Get type of resource associated with the class using this trait.
     *
     * @access protected
     * @return string
     * @throws \JsonApiHttp\Exceptions\JsonApiHttpException
     */
    protected function type()
    {
        throw new JsonApiHttpException('Method implementation missing');
    }
}
