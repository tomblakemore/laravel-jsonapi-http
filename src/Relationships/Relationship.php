<?php

namespace JsonApiHttp\Relationships;

use Illuminate\Support\Collection;
use JsonApiHttp\Contracts\Relationship as RelationshipContract;
use JsonApiHttp\Links;

class Relationship extends Collection implements RelationshipContract
{
    /**
     * A collection of relations outside of the parent collection.
     *
     * @access protected
     * @var \Illuminate\Support\Collection
     */
    protected $relations;

    /**
     * By default hide the relation data in the serialisation.
     *
     * @access protected
     * @var bool
     */
    protected $showRelationData = false;

    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
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
    public function hasMany()
    {
        return $this->relations()->count() > 1;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $serialized = parent::jsonSerialize();

        if ($this->showRelationData === true) {

            if ($this->hasMany()) {

                $serialized['data'] = [];

                foreach ($this->relations() as $relation) {
                    $serialized['data'][] = $relation->jsonSerialize();
                }
            }

            else {

                $serialized['data'] = null;

                if (($relation = $this->relation())) {
                    $serialized['data'] = $relation->jsonSerialize();
                }
            }
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
     * @return \JsonApiHttp\Relation
     */
    public function relation()
    {
        return $this->relations()->first();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function relations()
    {
        if (!$this->relations) {
            $this->relations = new Collection;
        }

        return $this->relations;
    }

    /**
     * Show the relation data in the response.
     *
     * @return $this
     */
    public function showRelationData()
    {
        $this->showRelationData = true;
        return $this;
    }
}
