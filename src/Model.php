<?php

namespace JsonApiHttp;

use Illuminate\Database\Eloquent\Model as Eloquent;

use JsonApiHttp\Contracts\Model as ModelContract;
use JsonApiHttp\Exceptions\ModelException;

abstract class Model extends Eloquent implements ModelContract
{
    /**
     * The resource type.
     *
     * @var string
     */
    protected $type = '';

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
     * @throws \JsonApiHttp\Exceptions\ModelException
     */
    public function type()
    {
        if (!$this->type) {
            throw new ModelException('Missing type');
        }

        return $this->type;
    }
}
