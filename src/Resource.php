<?php

namespace JsonApiHttp;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use JsonApiHttp\Attributes;
use JsonApiHttp\Links;
use JsonApiHttp\Relationships;
use JsonApiHttp\Relationships\BelongsTo;
use JsonApiHttp\Relationships\HasMany;

class Resource extends Collection
{
    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        $this->put('id', array_get($items, 'id'));
        $this->put('type', array_get($items, 'type'));

        foreach (array_get($items, 'attributes', []) as $name => $value) {
            $this->attributes()->put($name, $value);
        }

        foreach (array_get($items, 'links', []) as $name => $link) {
            $this->links()->put($name, $link);
        }

        $meta = array_get($items, 'meta', []);

        if (!empty($meta)) {
            $this->put('meta', (new Collection($meta)));
        }

        $relationships = array_get($items, 'relationships', []);

        foreach ($relationships as $name => $relationship) {

            if (array_key_exists('data', $relationship)) {

                $data = array_get($relationship, 'data');

                if (is_array($data) && !Arr::isAssoc($data)) {
                    $relationship = new HasMany($relationship);
                    $relationship->hidePagination();
                } else {
                    $relationship = new BelongsTo($relationship);
                }

                $this->relationships()->add($name, $relationship);
            }
        }
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function attributes()
    {
        $attributes = $this->get('attributes', (new Attributes));

        if (!$this->has('attributes')) {
            $this->put('attributes', $attributes);
        }

        return $attributes;
    }

    /**
     * @return string
     */
    public function id()
    {
        return strval($this->get('id'));
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $serialized = parent::jsonSerialize();

        if (empty($serialized['id'])) {
            array_forget($serialized, 'id');
        }

        if (empty($serialized['attributes'])) {
            array_forget($serialized, 'attributes');
        }

        if (empty($serialized['links'])) {
            array_forget($serialized, 'links');
        }

        if (empty($serialized['meta'])) {
            array_forget($serialized, 'meta');
        }

        if (empty($serialized['relationships'])) {
            array_forget($serialized, 'relationships');
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
     * @return \Illuminate\Support\Collection
     */
    public function relationships()
    {
        $relationships = $this->get('relationships', (new Relationships));

        if (!$this->has('relationships')) {
            $this->put('relationships', $relationships);
        }

        return $relationships;
    }

    /**
     * @return string
     */
    public function type()
    {
        return strval($this->get('type'));
    }
}
