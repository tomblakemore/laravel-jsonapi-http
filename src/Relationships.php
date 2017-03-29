<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

use JsonApiHttp\Contracts\Relationship;

class Relationships extends Collection
{
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
