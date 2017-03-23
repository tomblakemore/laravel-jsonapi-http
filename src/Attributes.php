<?php

namespace JsonApiHttp;

use Illuminate\Support\Collection;

class Attributes extends Collection
{
    /**
     * {@inheritDoc}
     */
    public function jsonSerialize()
    {
        $serialized = parent::jsonSerialize();

        foreach ($serialized as $key => $value) {

            $date = \DateTime::createFromFormat('Y-m-d H:i:s', $value);

            if (is_string($value) && $date !== false) {
                $serialized[$key] = $date->format('Y-m-d\TH:i:s.u\Z');
            }
        }

        ksort($serialized);

        return $serialized;
    }

    /**
     * {@inheritDoc}
     */
    public function put($key, $value)
    {
        $key = preg_replace('/\_/', '-', snake_case($key));

        return parent::put($key, $value);
    }

    /**
     * Convert all keys to snake case for insertion into a database, plus 
     * format the date string into a DB format.
     *
     * @return array
     */
    public function toDbArray()
    {
        $dbArray = [];

        foreach ($this->toArray() as $key => $value) {

            $date = \DateTime::createFromFormat('Y-m-d\TH:i:s.u\Z', $value);

            if (is_string($value) && $date !== false) {
                $value = $date->format('Y-m-d H:i:s');
            }

            $dbArray[preg_replace('/\-/', '_', $key)] = $value;
        }

        return $dbArray;
    }
}
