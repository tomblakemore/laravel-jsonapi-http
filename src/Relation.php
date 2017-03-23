<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

class Relation extends Collection
{
    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        if ($items instanceof Model) {

            $items = [
                'id' => $items->getRouteKey(),
                'type' => $items->type()
            ];
        }

        $this->put('id', array_get($items, 'id'));
        $this->put('type', array_get($items, 'type'));
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

        if (empty($serialized['type'])) {
            array_forget($serialized, 'type');
        }

        ksort($serialized);

        return $serialized;
    }

    /**
     * @return string
     */
    public function type()
    {
        return strval($this->get('type'));
    }
}
