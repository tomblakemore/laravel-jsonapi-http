<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

class Error extends Collection
{
    /**
     * {@inheritDoc}
     */
    public function __construct($items = [])
    {
        $this->put('id', array_get($items, 'id'));
        $this->put('code', array_get($items, 'code'));
        $this->put('detail', array_get($items, 'detail'));
        $this->put('meta', (new Collection(array_get($items, 'meta', []))));
        $this->put('source', (new Collection(array_get($items, 'source', []))));
        $this->put('status', array_get($items, 'status'));
        $this->put('title', array_get($items, 'title'));

        foreach (array_get($items, 'links', []) as $name => $link) {
            $this->links()->put($name, $link);
        }
    }

    /**
     * @return string
     */
    public function code()
    {
        return strval($this->get('code'));
    }

    /**
     * @return string
     */
    public function detail()
    {
        return strval($this->get('detail'));
    }

    /**
     * @return string
     */
    public function id()
    {
        return strval($this->get('id'));
    }

    /**
     * @return \JsonApiHttp\Links
     */
    public function links()
    {
        $links = $this->get('links', (new Links));

        if (!$this->has('links')) {
            $this->put('links', $links);
        }

        return $links;
    }

    /**
     * @return \JsonApiHttp\Meta
     */
    public function meta()
    {
        $meta = $this->get('meta', (new Meta));

        if (!$this->has('meta')) {
            $this->put('meta', $meta);
        }

        return $meta;
    }

    /**
     * @return \JsonApiHttp\Source
     */
    public function source()
    {
        $source = $this->get('source', (new Source));

        if (!$this->has('source')) {
            $this->put('source', $source);
        }

        return $source;
    }

    /**
     * @return string
     */
    public function status()
    {
        return strval($this->get('status'));
    }

    /**
     * @return string
     */
    public function title()
    {
        return strval($this->get('title'));
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
