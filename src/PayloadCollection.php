<?php

namespace JsonApiHttp;

use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use JsonApiHttp\Model;
use JsonApiHttp\Resource;

class PayloadCollection extends Payload
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

            foreach ($items as $resource) {

                if ($resource instanceof Model) {

                    $this->resources()->push(
                        new Resource([
                            'id' => $resource->getRouteKey(),
                            'type' => $resource->type(),
                            'attributes' => $resource->attributesToArray(),
                            'meta' => $resource->meta()
                        ])
                    );
                }

                elseif ($resource instanceof Resource) {
                    $this->resources()->push($resource);
                }

                else {
                    $this->resources()->push((new Resource($resource)));
                }
            }
        }

        else {
            parent::__construct($items);
        }
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
        if ($this->showPagination) {

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

        $serialized['data'] = [];

        foreach ($this->resources() as $resource) {
            $serialized['data'][] = $resource->jsonSerialize();
        }

        ksort($serialized);

        return $serialized;
    }

    /**
     * Create a paginator from the resources.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginator()
    {
        $items = $this->resources();
        $total = $items->count();
        $perPage = self::DEFAULT_PER_PAGE;
        $page = 1;

        if (($pagination = $this->meta()->get('pagination'))) {
            $total = $pagination['total'];
            $perPage = $pagination['per-page'];
            $page = $pagination['current-page'];
        }

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * {@inheritDoc}
     */
    public function resource()
    {
        return null;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function resources()
    {
        if (!$this->resources) {
            $this->resources = new Collection;
        }

        return $this->resources;
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
