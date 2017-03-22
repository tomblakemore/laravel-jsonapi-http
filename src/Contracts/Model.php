<?php

namespace JsonApiHttp\Contracts;

interface Model
{
    /**
     * Get the fillable relations for the model.
     *
     * @return array
     */
    public function getFillableRelations();

    /**
     * Return any meta data about the object in the JSON:API output.
     *
     * @return array
     */
    public function meta();

    /**
     * Return the number of objects per page in the JSON:API list outputs.
     *
     * @return int
     * @static
     */
    public static function perPage();

    /**
     * Return any the relations to display in the JSON:API output.
     *
     * @return array
     */
    public function relations();

    /**
     * Return the type of the object for the JSON:API output.
     *
     * @return string
     */
    public function type();
}
