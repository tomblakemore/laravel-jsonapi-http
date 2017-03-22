<?php

namespace JsonApiHttp\Contracts;

interface Relationship
{
    /**
     * @return bool
     */
    public function hasMany();
}
