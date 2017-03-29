<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

class Relations extends Collection
{
    /**
     * Extend the get function to also check in the collection for a relation 
     * with and id matching the key.
     *
     * {@inheritDoc}
     */
    public function get($key, $default = null)
    {
        if (!$this->offsetExists($key)) {

            foreach ($this->items as $item) {

                if ($item instanceof Relation && $item->id() === $key) {
                    return $item;
                }
            }
        }

        return parent::get($key, $default);
    }
}
