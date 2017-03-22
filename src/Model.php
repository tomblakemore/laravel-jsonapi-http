<?php

namespace App;

use Illuminate\Database\Eloquent\Model as Eloquent;
use JsonApiHttp\Contracts\Model as ModelContract
use JsonApiHttp\Exceptions\ModelTypeException;

abstract class Model extends Eloquent implements ModelContract
{
    /**
     * Default sorting string.
     *
     * @static
     * @var string
     */
    public static $sort = '-id';

    /**
     * Get the fillable relations for the model.
     *
     * @return array
     */
    public function getFillableRelations()
    {
        return $this->relations();
    }

    /**
     * Return any meta data about the object in the JSON:API output.
     *
     * @return array
     */
    public function meta()
    {
        return [];
    }

    /**
     * Return the number of objects per page in the JSON:API list outputs.
     *
     * @return int
     */
    public static function perPage()
    {
        return (new static)->perPage;
    }

    /**
     * Return any the relations to display in the JSON:API output.
     *
     * @return array
     */
    public function relations()
    {
        return [];
    }

    /**
     * Return the type of the object for the JSON:API output.
     *
     * @return string
     * @throws \JsonApiHttp\Exceptions\ModelTypeException
     */
    public function type()
    {
        $name = class_basename($this);

        throw new ModelTypeException('Unknown JSON:API type');
    }
}
