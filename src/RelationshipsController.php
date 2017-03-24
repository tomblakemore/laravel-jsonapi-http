<?php

namespace JsonApiHttp;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

use JsonApiHttp\Contracts\Model;
use JsonApiHttp\Exceptions\ControllerException;
use JsonApiHttp\Relationships\BelongsTo as BelongsToRelationship;
use JsonApiHttp\Relationships\HasMany as HasManyRelationship;

class RelationshipsController extends Controller
{
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
     * Build a relations payload .
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Contracts\Model $model
     * @param string $relation
     * @return \Illuminate\Http\Response
     */
    protected function relations(Request $request, Model $model, $relation)
    {
        $related = $model->{$relation}();

        if ($related instanceof BelongsTo) {

            if ((!$relatedItem = $related->first())) {
                return $response->setStatusCode(404);
            }

            $payload = new Payload($relatedItem);

            $relatedItems = (new Collection)->push($relatedItem);
        }

        else { // BelongsToMany or HasMany

            $relatedItems = $this->relatedItems($request, $related);

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

        return $payload;
    }

    /**
     * Build a relationship object for the "cru" responses.
     *
     * @access protected
     * @param \JsonApiHttp\Request $request
     * @param \JsonApiHttp\Contracts\Model $model
     * @param string $relation
     * @return \JsonApiHttp\Relationships\Relationship
     */
    protected function relationship(Request $request, Model $model, $relation)
    {
        $related = $model->{$relation}();

        if ($related instanceof BelongsTo) {

            $relationship = new BelongsToRelationship;

            if (($relatedItem = $related->first())) {
                $relationship->relations()->push(new Relation($relatedItem));
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

            $relatedItems = $this->relatedItems($request, $related);

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
}
