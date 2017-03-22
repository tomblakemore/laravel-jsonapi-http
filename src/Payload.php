<?php

namespace JsonApiHttp;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonApiHttp\Error;
use JsonApiHttp\Included;
use JsonApiHttp\Links;
use JsonApiHttp\Resource;

class Payload extends Collection
{
    /**
     * A collection of resources outside of the parent collection.
     *
     * @access protected
     * @var \Illuminate\Support\Collection
     */
    protected $resources;

    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        $data = array_get($items, 'data', []);

        if (Arr::isAssoc($data)) {
            $data = array($data);
        }

        foreach ($data as $resource) {

            if (($type = array_get($resource, 'type'))) {
                $this->resources()->push((new Resource($resource)));
            }
        }

        foreach (array_get($items, 'errors', []) as $error) {
            $this->errors()->push((new Error($error)));
        }

        foreach (array_get($items, 'included', []) as $include) {
            $this->included()->push((new Resource($include)));
        }

        foreach (array_get($items, 'links', []) as $name => $link) {
            $this->links()->put($name, $link);
        }

        $meta = array_get($items, 'meta', []);

        if (!empty($meta)) {
            $this->put('meta', (new Collection($meta)));
        }
    }

    /**
     * @return bool
     */
    public function error()
    {
        if (!$this->errors()->isEmpty()) {
            return true;
        }

        if (mb_strlen($this->message()) > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function errors()
    {
        $errors = $this->get('errors', (new Collection));

        if (!$this->has('errors')) {
            $this->put('errors', $errors);
        }

        return $errors;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function included()
    {
        $included = $this->get('included', (new Included));

        if (!$this->has('included')) {
            $this->put('included', $included);
        }

        return $included;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $serialized = parent::jsonSerialize();

        if (($resource = $this->resource())) {
            $serialized['data'] = $resource->jsonSerialize();
        }

        if (empty($serialized['errors'])) {
            array_forget($serialized, 'errors');
        }

        if (empty($serialized['included'])) {
            array_forget($serialized, 'included');
        }

        if (empty($serialized['links'])) {
            array_forget($serialized, 'links');
        }

        if (empty($serialized['meta'])) {
            array_forget($serialized, 'meta');
        }

        ksort($serialized);

        return $serialized;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function links()
    {
        $links = $this->get('links', (new Links));

        if (!$this->has('links')) {
            $this->put('links', $links);
        }

        return $links;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function meta()
    {
        $meta = $this->get('meta', (new Collection));

        if (!$this->has('meta')) {
            $this->put('meta', $meta);
        }

        return $meta;
    }

    /**
     * @return \App\Http\JsonApi\Resource
     */
    public function resource()
    {
        if ($this->resources()->count() === 1) {
            return $this->resources()->first();
        }
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
}
