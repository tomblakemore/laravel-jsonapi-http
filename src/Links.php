<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

class Links extends Collection
{
    /**
     * {@inheritDoc}
     */
    public function put($key, $value)
    {
        if (!($value instanceof Link)) {
            $value = new Link($value);
        }

        return parent::put($key, $value);
    }
}
