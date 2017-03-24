# Laravel JSON:API Package

## Description

A package for Laravel projects supporting the [JSON:API](http://jsonapi.org) 
specification.

## Installation using Composer

Use [Composer](https://getcomposer.org/) to install this package. If you don't 
have Composer already installed, then install as per the 
[documentation](https://getcomposer.org/doc/00-intro.md).

Inside your application folder run:

    composer require tomblakemore/laravel-jsonapi-http

## Setup

Add the service provider to the `config/app.php` file.

    'providers' => [
        ...

        JsonApiHttp\RequestServiceProvider::class
    ],

Add the following middleware groups to the `app\Http\Kernel.php` class.

    protected $middlewareGroups = [
        ...

        'jsonapi' => [
            \JsonApiHttp\Middleware\CheckAcceptHeaders::class,
            \JsonApiHttp\Middleware\CheckForContentTypeHeader::class,
            \JsonApiHttp\Middleware\VerifyContentTypeHeader::class,
            \JsonApiHttp\Middleware\VerifyContent::class,
            \JsonApiHttp\Middleware\SetResponseHeaders::class
        ],

        'relationships' => [
            \JsonApiHttp\Middleware\VerifyRelationship::class,
            \JsonApiHttp\Middleware\AddRelationshipToModel::class,
            \JsonApiHttp\Middleware\RemoveRelationshipFromModel::class
        ],

        'resources' => [
            \JsonApiHttp\Middleware\VerifyResource::class,
            \JsonApiHttp\Middleware\AddResourceAttributesToModel::class,
            \JsonApiHttp\Middleware\AddResourceRelationshipsToModel::class,
            \JsonApiHttp\Middleware\RemoveResourceRelationshipsFromModel::class
        ]
    ];

## Usage

Below is a simple model and controller example for showing a list of people 
and fetching a specific person.

The `Person` model defines the `name` attribute the property `$type`. The type 
is the JSON:API resource type and should be the plural representation of the 
model name.

    <?php

    namespace App;

    use JsonApiHttp\Model;

    class Person extends Model
    {
        /**
         * The attributes that are mass assignable.
         *
         * @var array
         */
        protected $fillable = [
            'name'
        ];

        /**
         * The resource type.
         *
         * @var string
         */
        protected $type = 'people';
    }

A controller with two actions to fetch people and a person.

    <?php

    namespace App\Http\Controllers;

    use App\Person;

    use JsonApiHttp\Controller;
    use JsonApiHttp\Request;

    class PersonController extends Controller
    {
        /**
         * The resource type.
         *
         * @var string
         */
        protected $type = 'people';

        /**
         * Return a list of people resources.
         *
         * @param \JsonApiHttp\Request $request
         * @return \Illuminate\Http\Response
         */
        public function index(Request $request)
        {
            $query = Person::query();

            $request->filter($query)->sort($query);

            $people = $request->paginate($query, Person::perPage());
            $people->appends($request->query());

            $payload = $this->payload($request, $people);

            return response($payload);
        }

        /**
         * Return a person resource.
         *
         * @param \JsonApiHttp\Request $request
         * @param \App\Person $person
         * @return \Illuminate\Http\Response
         */
        public function show(Request $request, Person $person)
        {
            $payload = $this->payload($request, $person);
            return response($payload);
        }
    }

Two routes to direct requests to the two controller actions.

    Route::group(['middleware' => ['jsonapi', 'resources']], function () {
        Route::get('/people', 'PersonController@index')->name('people.index');
        Route::get('/people/{person}', 'PersonController@show')->name('people.show');
    });

Example requests `GET /people` and `GET /people/:id`.

    GET /people HTTP/1.1
    Host: 127.0.0.1
    Accept: application/json

    {
        "data": [
            {
                "attributes": {
                    "created-at": "2017-03-24T02:12:37.000000Z",
                    "id": 1,
                    "name": "Tom",
                    "updated-at": "2017-03-24T02:12:37.000000Z"
                },
                "id": 1,
                "links": {
                    "self": "/people/1"
                },
                "type": "people"
            }
        ],
        "links": {
            "first": "/people?page=1",
            "last": "/people?page=1",
            "self": "/people?page=1"
        },
        "meta": {
            "pagination": {
                "total": 1,
                "per-page": 15,
                "current-page": 1,
                "page-count": 1,
                "from": 1,
                "to": 1
            }
        }
    }

    GET /people/1 HTTP/1.1
    Host: 127.0.0.1
    Accept: application/json

    {
        "data": {
            "attributes": {
                "created-at": "2017-03-24T02:12:37.000000Z",
                "id": 1,
                "name": "Tom",
                "updated-at": "2017-03-24T02:12:37.000000Z"
            },
            "id": 1,
            "links": {
                "self": "/people/1"
            },
            "type": "people"
        },
        "links": {
            "self": "/people/1"
        }
    }
