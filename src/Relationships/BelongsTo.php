<?php

namespace JsonApiHttp\Relationships;

use Illuminate\Support\Arr;
use JsonApiHttp\Relation;
use JsonApiHttp\Relationships\Relationship;

class BelongsTo extends Relationship
{
    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        parent::__construct($items);

        if (($relation = array_get($items, 'data'))) {

            if (Arr::isAssoc($relation)) {

                $id = array_get($relation, 'id');
                $type = array_get($relation, 'type');

                if ($id && $type) {
                    $this->relations()->push(
                        new Relation([
                            'id' => $id,
                            'type' => $type
                        ])
                    );
                }
            }
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasMany()
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $serialized = parent::jsonSerialize();

        if ($this->showRelationData === true) {

            $serialized['data'] = null;

            if (($relation = $this->relation())) {
                $serialized['data'] = $relation->jsonSerialize();
            }
        }

        return $serialized;
    }
}
