<?php

namespace JsonApiHttp\Relationships;

use Illuminate\Support\Arr;

use JsonApiHttp\Contracts\Model;
use JsonApiHttp\Relation;

class BelongsTo extends Relationship
{
    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        if ($items instanceof Model) {
            $this->relations()->push((new Relation($items)));
        }

        else {

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

            parent::__construct($items);
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
