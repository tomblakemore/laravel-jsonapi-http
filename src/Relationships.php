<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;
use JsonApiHttp\Contracts\Relationship;

class Relationships extends Collection
{
    /**
     * Add a new relationship item to the collection.
     *
     * @param string $name
     * @param \JsonApiHttp\Contracts\Relationship $relationship
     * @return $this
     */
    public function add($name, Relationship $relationship)
    {
        if (!$this->has($name)) {
            $this->put($name, $relationship);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $serialized = parent::jsonSerialize();

        foreach ($serialized as $key => $value) {

            if (empty($value)) {
                array_forget($serialized, $key);
            }
        }

        ksort($serialized);

        return $serialized;
    }
}