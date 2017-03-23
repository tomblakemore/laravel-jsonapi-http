<?php

namespace JsonApiHttp;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PayloadCollection extends Payload
{
    /**
     * Default page size of the paginated set.
     *
     * @var int
     */
    const DEFAULT_PER_PAGE = 15;

    /**
     * A paginated set of resources or models.
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

                if ($item instanceof Resource) {
                    $this->resources()->push($item);
                } else {
                    $this->resources()->push((new Resource($item)));
                }
            }
        }

        elseif ($items instanceof LengthAwarePaginator) {
            $this->setPaginator($items);
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

        $serialized['data'] = [];

        foreach ($this->resources() as $resource) {
            $serialized['data'][] = $resource->jsonSerialize();
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
            $total = 0;
            $perPage = self::DEFAULT_PER_PAGE;
            $page = 1;

            if ($this->resources) {

                $items = $this->resources;
                $total = $items->count();

                if (($pagination = $this->meta()->get('pagination'))) {
                    $total = $pagination['total'];
                    $perPage = $pagination['per-page'];
                    $page = $pagination['current-page'];
                }
            }

            $this->paginator = new LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $page
            );
        }

        return $this->paginator;
    }

    /**
     * {@inheritDoc}
     */
    public function resource()
    {
        return null;
    }

    /**
     * Set a results paginator to the payload.
     *
     * @param \Illuminate\Pagination\LengthAwarePaginator $paginator
     * @return $this
     */
    public function setPaginator(LengthAwarePaginator $paginator)
    {
        $this->paginator = $paginator;

        $this->resources = new Resources;

        $this->paginator->getCollection()->each(function($item, $key) {
            $this->resources->put($key, (new Resource($item)));
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
