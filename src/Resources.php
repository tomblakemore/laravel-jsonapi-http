<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

class Resources extends Collection
{
    /**
     * Extend the get function to also check in the collection for a resource 
     * with an id matching the key.
     *
     * {@inheritDoc}
     */
    public function get($key, $default = null)
    {
        if (!$this->offsetExists($key)) {

            foreach ($this->items as $item) {

                if ($item instanceof Resource && $item->id() === $key) {
                    return $item;
                }
            }
        }

        return parent::get($key, $default);
    }
}
