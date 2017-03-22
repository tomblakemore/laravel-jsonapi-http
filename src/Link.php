<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

class Link extends Collection
{
    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        if (is_string($items)) {
            $this->put('href', $items);
        }

        if (is_array($items)) {

            $this->put('href', array_get($items, 'href'));

            $meta = array_get($items, 'meta', []);

            if (!empty($meta)) {
                $this->put('meta', (new Collection($meta)));
            }
        }
    }

    /**
     * @return string
     */
    public function href()
    {
        return strval($this->get('href'));
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
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $serialized = parent::jsonSerialize();

        if (empty($serialized['meta'])) {

            array_forget($serialized, 'meta');

            if (!empty($serialized['href'])) {
                $serialized = $serialized['href'];
            }
        }

        if (is_array($serialized)) {
            ksort($serialized);
        }

        return $serialized;
    }
}
