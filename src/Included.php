<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;
use JsonApiHttp\Resource;

class Included extends Collection
{
    /**
     * {@inheritDoc}
     */
    public function push($value)
    {
        if ($value instanceof Resource) {

            foreach ($this->items as $item) {
                if ($item->id() === $value->id()
                        && $item->type() === $value->type()) {
                    return $this;
                }
            }

            return parent::push($value);
        }

        return $this;
    }
}
