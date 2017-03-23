<?php

namespace JsonApiHttp\Relationships;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

use JsonApiHttp\Relation;
use JsonApiHttp\Relations;

class HasMany extends Relationship
{
    /**
     * Default page size of the paginated set.
     *
     * @var int
     */
    const DEFAULT_PER_PAGE = 15;

    /**
     * A paginated set of relations or models.
     *
     * @access protected
     * @var \Illuminate\Pagination\LengthAwarePaginator
     */
    protected $paginator;

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
        if ($items instanceof Collection) {

            foreach ($items as $item) {

                if ($item instanceof Relation) {
                    $this->relations()->push($item);
                } else {
                    $this->relations()->push((new Relation($item)));
                }
            }
        }

        elseif ($items instanceof LengthAwarePaginator) {
            $this->setPaginator($items);
        }

        else {

            parent::__construct($items);

            $data = array_get($items, 'data', []);

            if (!Arr::isAssoc($data) && !empty($data)) {

                $this->showRelationData();

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
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginator()
    {
        if (!$this->paginator) {

            $items = new Collection;

            if ($this->relations) {
                $items = $this->relations;
            }

            $this->paginator = new LengthAwarePaginator(
                $items,
                $items->count(),
                self::DEFAULT_PER_PAGE
            );
        }

        return $this->paginator;
    }

    /**
     * {$inheritDoc}
     */
    public function relation()
    {
        return null;
    }

    /**
     * Set a results paginator to the relationship.
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @return $this
     */
    public function setPaginator(LengthAwarePaginator $paginator)
    {
        $this->paginator = $paginator;

        $this->relations = new Relations;

        $this->paginator->getCollection()->each(function($item, $key) {
            $this->relations->put($key, (new Relation($item)));
        });

        return $this;
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
