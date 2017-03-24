<?php

namespace JsonApiHttp\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

use JsonApiHttp\Exceptions\ControllerException;
use JsonApiHttp\Relationships\BelongsTo as BelongsToRelationship;
use JsonApiHttp\Relationships\HasMany as HasManyRelationship;

class RelationshipsController extends Controller
{
    /**
     * Return the total number of resources.
     *
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Model $model
     * @param string $relation
     * @return \Illuminate\Http\Response
     */
    public function count(Request $request, Model $model, $relation)
    {
        $response = new Response;

        $query = $model->{$relation}();

        $request->filter($query);

        $payload = new Payload([
            'meta' => [
                'count' => $query->count()
            ]
        ]);

        $response->setContent($payload);
        $response->setStatusCode(200);

        return $response;
    }

    /**
     * Fetch a resource or collection of resources for a relationship.
     *
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Model $model
     * @param string $relation
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, Model $model, $relation)
    {
        $response = new Response;

        $related = $model->{$relation}();

        if ($related instanceof BelongsTo) {

            if ((!$relatedItem = $related->first())) {
                return $response->setStatusCode(404);
            }

            $payload = new Payload($relatedItem);

            $relatedItems = (new Collection)->push($relatedItem);
        }

        else { // BelongsToMany or HasMany

            if (!($relatedItems = $this->relatedItems($request, $related))) {
                return $response->setStatusCode(404);
            }

            $payload = new PayloadCollection($relatedItems);

            $payload->paginator()->setPath(
                route("{$model->type()}.relations.index", [
                    'id' => $model->getRouteKey(),
                    'relation' => $relation
                ],
                false
            ));
        }

        $this->addResources($request, $payload, $relatedItems);

        $response->setContent($payload);
        $response->setStatusCode(200);

        return $response;
    }

    /**
     * Get paginated items for a request passing an optional starting query.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param mixed $query
     * @return \Illuminate\Pagination\LengthAwarePaginator
     * @throws \JsonApiHttp\Exceptions\ControllerException
     */
    protected function relatedItems(Request $request, $query)
    {
        $model = $query->getRelated();

        $request->filter($query)->sort($query);

        $paginator = $request->paginate($query, $model::perPage());

        $paginator->appends($request->query());

        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();

        if ($currentPage > $lastPage) {
            throw new ControllerException('Invalid paginator');
        }

        return $paginator;
    }

    /**
     * Build a relationship object for the "cru" responses.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Model $model
     * @param string $relation
     * @return \JsonApiHttp\Relationships\Relationship
     */
    protected function relationship(Request $request, Model $model, $relation)
    {
        $related = $model->{$relation}();

        if ($related instanceof BelongsTo) {

            $relationship = new BelongsToRelationship;

            if (($relatedItem = $related->first())) {

                $relationship->relations()->push(
                    new Relation([
                        'id' => $relatedItem->getRouteKey(),
                        'type' => $relatedItem->type()
                    ])
                );
            }

            $relationship->links()->put('self',
                route("{$model->type()}.relations.show", [
                    'id' => $model->getRouteKey(),
                    'relation' => $relation
                ],
                false
            ));
        }

        else { // BelongsToMany or HasMany

            $relatedItems = $this->relatedItems($request, $related)

            $relationship = new HasManyRelationship($relatedItems);

            $relationship->paginator()->setPath(
                route("{$model->type()}.relations.show", [
                    'id' => $model->getRouteKey(),
                    'relation' => $relation
                ],
                false
            ));
        }

        $relationship->showRelationData();

        $relationship->links()->put('related',
            route("{$model->type()}.relations.index", [
                'id' => $model->getRouteKey(),
                'relation' => $relation
            ],
            false
        ));

        return $relationship;
    }

    /**
     * Display a relationship.
     *
     * @param \App\Http\Requests\JsonApiRequest $request
     * @param \App\Model $model
     * @param string $relation
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Model $model, $relation)
    {
        $response = new Response;

        $relationship = $this->relationship($request, $model, $relation);

        $response->header('X-Resource-Id', $model->getRouteKey());
        $response->setContent($relationship);
        $response->setStatusCode(200);

        return $response;
    }
}
