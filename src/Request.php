<?php

namespace JsonApiHttp;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Arr;

use JsonApiHttp\Contracts\Model;
use JsonApiHttp\Exceptions\ControllerException;
use JsonApiHttp\Exceptions\ModelException;
use JsonApiHttp\Exceptions\RequestException;
use JsonApiHttp\Relationships\BelongsTo as BelongsToRelationship;
use JsonApiHttp\Relationships\HasMany as HasManyRelationship;

use Symfony\Component\HttpKernel\Exception\HttpException;

class Request extends HttpRequest
{
    /**
     * @access protected
     * @var \JsonApiHttp\Contract\Model
     */
    protected $model;

    /**
     * @access protected
     * @var \JsonApiHttp\Payload
     */
    protected $payload;

    /**
     * @access protected
     * @var int
     */
    protected $perPage = 15;

    /**
     * @access protected
     * @var \JsonApiHttp\Contracts\RelationshipInterface
     */
    protected $relationship;

    /**
     * Append an expression to the ongoing query (called recursively).
     *
     * @access protected
     * @param string $expression
     * @param \Illuminate\Database\Query\Builder $query
     * @param array $pairs
     * @param array $expressions
     * @param array $ins
     * @return void
     * @throws \JsonApiHttp\Exceptions\RequestException
     */
    protected function addExpressionToQuery(
        $expression,
        $query,
        array $pairs = [],
        array $expressions = [],
        array $ins = []
    )
    {
        // We need a valid model attached the query to be able to check the key 
        // names against the allowed attributes, so if we don't have one then 
        // just quit the function.
        if (!($model = $query->getModel())) {
            return;
        }

        // If our expression contains no pipes then we must be doing an AND 
        // type boolean query. If it contains just pipes then we must be doing 
        // an OR type boolean query. If neither of those two things are true 
        // then the expression must contain a mixture of commas and pipes, in 
        // which case this expression is too ambiguous to add to the query so 
        // we're probably best off leaving it out.
        if (($pipes = substr_count($expression, '|')) === 0) {
            $boolean = 'AND';
        } elseif (substr_count($expression, ',') === 0 && $pipes > 0) {
            $boolean = 'OR';
        } else {
            throw new RequestException('Bad request');
        }

        $items = explode($boolean === 'AND' ? ',' : '|', $expression);

        foreach ($items as $item) {

            /*
            |------------------------------------------------------------------
            | Included expressions
            |------------------------------------------------------------------
            |
            | Go through each item in the AND or OR expression and decide first 
            | whether the item represents another expression - if it does call 
            | the same function recursively with the correct AND or OR wrapping 
            | around the call (so that it's contained within it's own 
            | parenthesis inside the query).
            |
            */

            $pattern = '/^{{expression-[a-z0-9]{4}}}$/i';

            if (preg_match($pattern, $item)) {

                if (array_key_exists($item, $expressions)) {

                    $method = $boolean === 'AND' ? 'where' : 'orWhere';

                    $query->{$method}(function($q) use (
                        $item,
                        $expressions,
                        $pairs,
                        $ins
                    ) {
                        $this->addExpressionToQuery(
                            $expressions[$item],
                            $q,
                            $pairs,
                            $expressions,
                            $ins
                        );
                    });
                }

                continue;
            }

            /*
            |------------------------------------------------------------------
            | Key:value pairings
            |------------------------------------------------------------------
            |
            | If the item isn't an expression then it's probably a key:value 
            | pairing, in which case we need to break this down into the key 
            | and value and decide what operator to use in the AND or OR where 
            | clause. Native PHP types may also need to be cast, as well as 
            | relations taken care of using has sub-queries.
            |
            */

            $pattern = '/^{{pair-[a-z0-9]{4}}}$/i';

            if (preg_match($pattern, $item)) {

                if (array_key_exists($item, $pairs)) {

                    $pair = trim($pairs[$item]);

                    $pattern = '/^([^\:]+)\:(.*)$/i';

                    if (preg_match($pattern, $pair)) {

                        $key = snake_case(preg_replace($pattern, '$1', $pair));
                        $key = preg_replace('/\-/', '_', $key);
                        $key = strtolower($key);

                        $value = preg_replace($pattern, '$2', $pair);

                        $pattern = '/^{{in-[a-z0-9]{4}}}$/i';

                        if (preg_match($pattern, $value)) {

                            if (array_key_exists($value, $ins)) {
                                $value = $ins[$value];
                            }
                        }

                        $method = $boolean === 'AND' ? 'where' : 'orWhere';

                        /*
                        |------------------------------------------------------
                        | Relations
                        |------------------------------------------------------
                        |
                        | If the key is recognised as a relation (rather than 
                        | an attribute) then they must be handled with either a
                        | subquery if there's a join table, or by looking up 
                        | the foreign key and using that in the query.
                        |
                        */

                        if (in_array($key, $model->relations())) {

                            if (is_string($value) && $value === 'null') {

                                $relationMethod = snake_case(camel_case($key));

                                $relation = $model->{$relationMethod}();

                                if ($relation instanceof BelongsTo) {
                                    $foreignKey = $relation->getForeignKey();
                                    $query->{$method.'Null'}($foreignKey);
                                }

                                continue;
                            }

                            $ids = [];

                            if (is_string($value)) {
                                $ids = (array) $value;
                            } elseif (is_array($value)) {
                                $ids = $value;
                            }

                            $query->{$method.'Has'}(
                                $key, function($subquery) use ($model, $ids) {
                                    $subquery->whereIn(
                                        $model->getRouteKeyName(),
                                        $ids
                                    );
                                }
                            );

                            continue;
                        }

                        // From this point onwards we should only consider 
                        // visible keys as keys we can filter/query on, so if 
                        // this key does not comply we should skip it.
                        if (!empty($model->getVisible())
                                && !in_array($key, $model->getVisible())) {
                            continue;
                        }

                        // If the value has been identified as an array (above) 
                        // then it must be inserted as an IN query rather than 
                        // as a standard type with an operator.
                        if (is_array($value)) {

                            if (!empty($value)) {
                                $query->{$method.'In'}($key, $value);
                            }

                            continue;
                        }

                        // If we've got this far then we're dealing with a 
                        // value currently represented as a string, so if it's 
                        // empty and not equal to the zero string then we may 
                        // as well skip to the next value.
                        if (empty($value) && $value !== '0') {
                            continue;
                        }

                        /*
                        |------------------------------------------------------
                        | Choice of operator
                        |------------------------------------------------------
                        |
                        | Determine the type of operator to use in the where 
                        | clause by looking at the first one or two characters 
                        | of the value.
                        |
                        | Supported values include:
                        |
                        |    - '=', '!', '<', '>', '^', '$'
                        |    - '!=', '<=', '>=', '<>'
                        |
                        */

                        $operator = '=';

                        $singles = ['=', '!', '<', '>', '^', '$'];
                        $doubles = ['!=', '<=', '>=', '<>', '!^', '!$', '^$'];
                        $triples = ['!^$'];

                        if (in_array(substr($value, 0, 3), $triples)) {

                            switch (substr($value, 0, 3)) {

                                case '!^$':
                                    $operator = 'not like';
                                    $value = '%' . substr($value, 3) . '%';
                                    break;
                            }
                        }

                        elseif (in_array(substr($value, 0, 2), $doubles)) {

                            switch (substr($value, 0, 2)) {

                                case '!=':
                                    $operator = '!=';
                                    $value = substr($value, 2);
                                    break;

                                case '<=':
                                    $operator = '<=';
                                    $value = substr($value, 2);
                                    break;

                                case '>=':
                                    $operator = '>=';
                                    $value = substr($value, 2);
                                    break;

                                case '<>':
                                    $operator = '<>';
                                    $value = substr($value, 2);
                                    break;

                                case '!^':
                                    $operator = 'not like';
                                    $value = substr($value, 2) . '%';
                                    break;

                                case '!$':
                                    $operator = 'not like';
                                    $value = '%' . substr($value, 2);
                                    break;

                                case '^$':
                                    $operator = 'like';
                                    $value = '%' . substr($value, 2) . '%';
                                    break;
                            }
                        }

                        elseif (in_array(substr($value, 0, 1), $singles)) {

                            switch (substr($value, 0, 1)) {

                                case '=':
                                    $operator = '=';
                                    $value = substr($value, 1);
                                    break;

                                case '!':
                                    $operator = '!=';
                                    $value = substr($value, 1);
                                    break;

                                case '<':
                                    $operator = '<';
                                    $value = substr($value, 1);
                                    break;

                                case '>':
                                    $operator = '>';
                                    $value = substr($value, 1);
                                    break;

                                case '^':
                                    $operator = 'like';
                                    $value = substr($value, 1) . '%';
                                    break;

                                case '$':
                                    $operator = 'like';
                                    $value = '%' . substr($value, 1);
                                    break;
                            }
                        }

                        /*
                        |------------------------------------------------------
                        | Value casting / insertion
                        |------------------------------------------------------
                        |
                        | Check the value and/or model cast type to determine 
                        | how it should be inserted into the query.
                        |
                        */

                        if ($value === 'null') {

                            if ($operator === '=') {
                                $query->{$method.'Null'}($key);
                            } elseif (in_array($operator, ['!=', '<>'])) {
                                $query->{$method.'NotNull'}($key);
                            }

                            continue;
                        }

                        if ($model->hasCast($key, 'boolean')) {

                            if (strtolower($value) === 'true') {
                                $value = true;
                            } elseif (strtolower($value) === 'false') {
                                $value = false;
                            }

                            $value = boolval($value);
                            $query->{$method}($key, $operator, $value);
                            continue;
                        }

                        elseif ($model->hasCast($key, ['date', 'datetime'])) {
                            $query->{$method}($key, $operator, $value);
                            continue;
                        }

                        elseif ($model->hasCast($key, ['double', 'float'])) {
                            $value = floatval($value);
                            $query->{$method}($key, $operator, $value);
                            continue;
                        }

                        elseif ($model->hasCast($key, 'integer')) {
                            $value = intval($value);
                            $query->{$method}($key, $operator, $value);
                            continue;
                        }

                        else {
                            $value = strtolower($value);
                            $query->{$method}($key, $operator, $value);
                            continue;
                        }
                    }
                }

                continue;
            }
        }
    }

    /**
     * Filter a query.
     *
     * @param mixed $query
     * @return $this
     * @throws \JsonApiHttp\Exceptions\RequestException
     */
    public function filter($query)
    {
        /*
        |----------------------------------------------------------------------
        | Advanced filtering
        |----------------------------------------------------------------------
        |
        | The aim of the following string processing is to break a filter query 
        | down into a series of and/or where clauses, taking into account 
        | more complex queries using parenthesised phrases and options for 
        | multiple value matches on the same field.
        |
        | Examples:
        |
        |    - name:value => `name` = `value`
        |    - name:value,... => `name` = `value` AND ...
        |    - name:value|... => `name` = `value` OR ...
        |    - name:(value1|value2|...) => `name` IN ('value1','value2',...)
        |    - name:value,(name:value|name:value)
        |         => `name` = `value` AND (`name` = `value` OR `name` = `value`)
        |
        */

        $filter = trim($this->query('filter'));

        if (mb_strlen($filter) > 0) {

            if (substr_count($filter, '(') !== substr_count($filter, ')')) {
                throw new RequestException('Bad request');
            }

            $pattern = '/^\((.*)\)$/';

            if (!($nest = !empty($query->getBindings()))) {

                if (preg_match($pattern, $filter) !== 1) {
                    $filter = preg_replace($pattern, '$1', $filter);
                }
            }

            if ($nest && preg_match($pattern, $filter) !== 1) {
                $filter = '(' . $filter . ')';
            }

            /*
            |------------------------------------------------------------------
            | IN expressions
            |------------------------------------------------------------------
            |
            | Look for expressions like `name:(value|value|...)` and replace 
            | the terms in the parenthesis with placeholders.
            |
            */

            $ins = [];

            $pattern = '/(\((?:[^\(\)\:]*)\))/';

            preg_match_all($pattern, $filter, $matches);

            if (!empty($matches[1])) {

                foreach ($matches[1] as $match) {

                    $placeholder = '{{in-'.str_random(4).'}}';

                    $filter = str_ireplace($match, $placeholder, $filter);

                    $match = substr(substr($match, 0, -1), 1);

                    $ins[$placeholder] = explode('|', $match);
                }
            }

            /*
            |------------------------------------------------------------------
            | Key/value pairs
            |------------------------------------------------------------------
            |
            | Look for all name:value pairs in the string and replace with 
            | placeholders.
            |
            */

            $pairs = [];

            $pattern = '/([^\,\|\:\(]+\:[^\,\|\)]+)/';

            preg_match_all($pattern, $filter, $matches);

            if (!empty($matches[1])) {

                foreach ($matches[1] as $match) {

                    $placeholder = '{{pair-'.str_random(4).'}}';

                    $filter = str_ireplace($match, $placeholder, $filter);

                    $pairs[$placeholder] = $match;
                }
            }

            /*
            |------------------------------------------------------------------
            | Parenthesised expressions
            |------------------------------------------------------------------
            |
            | Look for any other expressions contained within parenthesis and 
            | replace those with further placeholders.
            |
            */

            $expressions = [];

            $pattern = '/(\((?:[^\(\)]*)\))/';

            while (preg_match($pattern, $filter) === 1) {

                preg_match_all($pattern, $filter, $matches);

                if (!empty($matches[1])) {

                    foreach ($matches[1] as $match) {

                        $placeholder = '{{expression-'.str_random(4).'}}';

                        $filter = str_ireplace(
                            $match,
                            $placeholder,
                            $filter
                        );

                        $match = substr(substr($match, 0, -1), 1);

                        $expressions[$placeholder] = $match;
                    }
                }
            }

            $this->addExpressionToQuery(
                $filter,
                $query,
                $pairs,
                $expressions,
                $ins
            );
        }

        return $this;
    }

    /**
     * Return the formatted includes array from the query string.
     *
     * @return array
     */
    public function includes()
    {
        $includes = [];

        if (($include = request()->query('include')) !== '*') {

            $include = array_map(
                function($relation) {
                    return explode('.', trim($relation));
                },
                explode(',', $include)
            );

            foreach ($include as $nested) {
                $key = array_shift($nested);
                $includes[$key] = $this->nestIncludes($nested);
            }
        }

        else {

            $className = '\App\\' . studly_case(str_singular($this->type()));

            foreach ((new $className)->relations() as $relation) {
                $includes[$relation] = [];
            }
        }

        return $includes;
    }

    /**
     * Get the model bound to the route or contruct from payload.
     *
     * @return \JsonApiHttp\Contract\Model
     * @throws \JsonApiHttp\Exceptions\ModelException
     */
    public function model()
    {
        if (!$this->model) {

            $type = snake_case(camel_case(str_singular($this->type())));

            $this->model = $this->route()->parameter($type);

            if (!$this->model && $this->method() === 'POST') {

                $name = studly_case($type);

                $className = "\App\\{$name}";

                $this->model = new $className;
            }

            if ($this->model && !($this->model instanceof Model)) {
                throw new ModelException('Invalid model');
            }
        }

        return $this->model;
    }

    /**
     * Create an associate array for one level of of nested includes.
     *
     * @access protected
     * @param array $unnested
     * @return array
     */
    protected function nestIncludes(array $unnested = [])
    {
        $nested = [];

        if (!empty($unnested)) {
            $key = array_shift($unnested);
            $nested[$key] = $this->nestIncludes($unnested);
        }

        return $nested;
    }

    /**
     * Create a new paginator from query.
     *
     * @param mixed $query
     * @param int|null $default
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function paginate($query, $default = null)
    {
        if (!$default) {
            $default = $this->perPage;
        }

        $perPage = max(0, $this->query('perPage', $default));

        return $query->paginate($perPage);
    }

    /**
     * Fetch the JsonApi payload from the request content.
     *
     * @return \JsonApiHttp\PayloadInterface
     */
    public function payload()
    {
        if (!$this->payload) {
            $this->payload = new Payload($this->json()->all());
        }

        return $this->payload;
    }

    /**
     * Accessor for the relationship.
     *
     * @return \JsonApiHttp\Contracts\RelationshipInterface
     */
    public function relationship()
    {
        if (!$this->relationship && ($model = $this->model())) {

            $json = $this->json()->all();

            if (array_key_exists('data', $json)) {

                $data = array_get($json, 'data');

                if (is_array($data) && !Arr::isAssoc($data)) {
                    $this->relationship = new HasManyRelationship($json);
                } else {
                    $this->relationship = new BelongsToRelationship($json);
                }
            }
        }

        return $this->relationship;
    }

    /**
     * Accessor for the relations of a relationship.
     *
     * @return \Illuminate\Support\Collection
     */
    public function relations()
    {
        return $this->relationship()->relations();
    }

    /**
     * Accessor for the relation of a "belongs to" relationship.
     *
     * @return \Illuminate\Support\Collection
     */
    public function relation()
    {
        return $this->relationship()->relation();
    }

    /**
     * Accessor for the payload's resource.
     *
     * @return \Illuminate\Support\Collection
     */
    public function resources()
    {
        return $this->payload()->resources();
    }

    /**
     * Accessor for the payload's resource.
     *
     * @return \JsonApiHttp\Resource
     */
    public function resource()
    {
        $name = $this->route()->getName();

        $routes = [
            '*.store',
            '*.show',
            '*.update',
            '*.destroy',
            '*.pending',
            '*.unmark-pending',
            '*.publish',
            '*.unpublish',
            '*.edit',
            '*.duplicate',
            '*.version',
            '*.move'
        ];

        foreach ($routes as $route) {

            if (str_is($route, $name)) {
                return $this->payload()->resource();
            }
        }
    }

    /**
     * Add sorting to a query.
     *
     * @param mixed $query
     * @return $this
     */
    public function sort(&$query)
    {
        if (($model = $query->getModel()) && ($sort = $this->query('sort'))) {

            $sortings = explode(',', $sort);

            foreach ($sortings as $sorting) {

                $sorting = trim($sorting);

                $pattern = '/^(\-)?([a-zA-Z0-9\_\-]+)$/i';

                if (preg_match($pattern, $sorting)) {

                    $key = snake_case(preg_replace($pattern, '$2', $sorting));
                    $key = preg_replace('/\-/', '_', $key);
                    $key = strtolower($key);

                    $direction = 'asc';

                    if (preg_replace($pattern, '$1', $sorting) === '-') {
                        $direction = 'desc';
                    }

                    if (!empty($model->getVisible())
                            && in_array($key, $model->getVisible())) {
                        $query->orderBy($key, $direction);
                    } elseif ($key === $model->getRouteKeyName()) {
                        $query->orderBy($key, $direction);
                    }

                    $scope = 'scope' . studly_case($key);

                    if (method_exists($model, $scope)) {
                        $query->{camel_case($key)}($direction);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Return the resource type of request from the controller.
     *
     * @return string
     * @throws \JsonApiHttp\Exceptions\ControllerException
     */
    public function type()
    {
        $action = $this->route()->getAction();

        list($class, $method) = explode('@', Arr::get($action, 'uses', []));

        $properties = (new \ReflectionClass($class))->getDefaultProperties();

        if (!($type = Arr::get($properties, 'type'))) {
            throw new ControllerException('Missing type');
        }

        return $type;
    }
}
