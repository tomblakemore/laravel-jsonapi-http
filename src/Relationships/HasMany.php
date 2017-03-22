<?php

namespace JsonApiHttp\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonApiHttp\Relation;
use JsonApiHttp\Relationships\Relationship;

class HasMany extends Relationship
{
    /**
     * Default page size of the paginated set.
     *
     * @var int
     */
    const DEFAULT_PER_PAGE = 15;

    /**
     * Show pagination in meta.
     *
     * @access protected
     * @var bool
     */
    protected $showPagination = true;

    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        if ($items instanceof Collection || $items instanceof Paginator) {

            foreach ($items as $relation) {
                $this->relations()->push((new Relation($relation)));
            }
        }

        else {

            parent::__construct($items);

            $data = array_get($items, 'data', []);

            if (!Arr::isAssoc($data)) {

                foreach ($data as $relation) {
                    $this->relations()->push((new Relation($relation)));
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasMany()
    {
        return true;
    }

    /**
     * Set to hide the pagination meta in the response.
     *
     * @return $this
     */
    public function hidePagination()
    {
        $this->showPagination = false;
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        if ($this->showPagination === true) {

            $paginator = $this->paginator();

            $total = intval($paginator->total());
            $perPage = intval($paginator->perPage());
            $currentPage = intval($paginator->currentPage());
            $pageCount = intval($paginator->lastPage());
            $from = intval($paginator->firstItem());
            $to = intval($paginator->lastItem());

            $firstPage = min(1, $pageCount);
            $lastPage = $pageCount;

            if (!$this->links()->has('first')
                    && ($first = $paginator->url($firstPage))) {
                $this->links()->put('first', $first);
            }

            if (!$this->links()->has('last')
                    && ($last = $paginator->url($lastPage))) {
                $this->links()->put('last', $last);
            }

            if (!$this->links()->has('next')
                    && ($next = $paginator->nextPageUrl())) {
                $this->links()->put('next', $next);
            }

            if (!$this->links()->has('prev')
                    && ($prev = $paginator->previousPageUrl())) {
                $this->links()->put('prev', $prev);
            }

            if (!$this->links()->has('self')
                    && ($self = $paginator->url($currentPage))) {
                $this->links()->put('self', $self);
            }

            $this->meta()->put('pagination', [
                'total' => $total,
                'per-page' => $perPage,
                'current-page' => $currentPage,
                'page-count' => $pageCount,
                'from' => $from,
                'to' => $to
            ]);
        }

        $serialized = parent::jsonSerialize();

        if ($this->showRelationData === true) {

            $serialized['data'] = [];

            foreach ($this->relations() as $relation) {
                $serialized['data'][] = $relation->jsonSerialize();
            }
        }

        ksort($serialized);

        return $serialized;
    }

    /**
     * Create a paginator from the relations.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginator()
    {
        $items = $this->relations();

        return new Paginator(
            $items,
            $items->count(),
            self::DEFAULT_PER_PAGE
        );
    }

    /**
     * {$inheritDoc}
     */
    public function relation()
    {
        return null;
    }

    /**
     * {$inheritDoc}
     */
    public function relations()
    {
        if (!$this->relations) {

            $this->relations = new Collection;

            foreach ($this->paginator() as $relation) {

                if ($relation instanceof Model) {
                    $this->relations->push(
                        new Relation([
                            'id' => $relation->getRouteKey(),
                            'type' => $relation->type()
                        ])
                    );
                }

                if ($relation instanceof Relation) {
                    $this->relations->push($relation);
                }
            }
        }

        return $this->relations;
    }

    /**
     * Set to show the pagination meta in the response.
     *
     * @return $this
     */
    public function showPagination()
    {
        $this->showPagination = true;
        return $this;
    }
}
