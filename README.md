# Laravel JSON:API Package

## Description

A package for Laravel projects supporting the [JSON:API](http://jsonapi.org) 
specification.

Features supported in the package:

* Relationships
* Includes
* Filtering
* Pagination
* Sorting

For full details of the JSON:API spec see 
[http://jsonapi.org/format](http://jsonapi.org/format).

## Installation using Composer

Use [Composer](https://getcomposer.org/) to install this package. If you don't 
have Composer already installed, then install as per the 
[documentation](https://getcomposer.org/doc/00-intro.md).

Inside your application folder run:

    composer require tomblakemore/laravel-jsonapi-http

## Tests

These will be added soon!

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
            \JsonApiHttp\Middleware\SetRequest::class,
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

## Basic usage

Below is a simple model and controller example for showing a list of people 
and fetching a specific person.

Create the `people` table.

    php artisan make:migration create_people_table --create=people

Add the below to the migration file.

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('people', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }

Create the `Person` model and fill in the property `$type`. The type is the 
JSON:API resource type and should be the plural representation of the model 
name, so in this case `people`.

    <?php

    namespace App;

    use JsonApiHttp\AbstractModel;

    class Person extends AbstractModel
    {
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
         * Return a list of people.
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
         * Return a person.
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

The resources are now accessible on the following endpoints:

* `GET /people`
* `GET /people/:id`

### Relationships

Objects can be represented by JSON:API relationship endpoints. We'll create a 
simple `Patient` > `Doctor` relationship as an example.

Create the `doctors` table.

    php artisan make:migration create_doctors_table --create=doctors

Add the below to the migration file.

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('doctors', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
        });
    }

Create the `Doctor` model and `patients` relationship.

    <?php

    namespace App;

    use JsonApiHttp\AbstractModel;

    class Doctor extends AbstractModel
    {
        /**
         * The resource type.
         *
         * @var string
         */
        protected $type = 'doctors';

        /**
         * @return \Illuminate\Database\Eloquent\Relations\HasMany
         */
        public function patients()
        {
            return $this->hasMany(Patient::class);
        }

        /**
         * Return any the relations to display in the JSON:API output.
         *
         * @return array
         */
        public function relations()
        {
            return ['patients'];
        }
    }

Create a controller for the relationships.

    <?php

    namespace App\Http\Controllers;

    use App\Doctor;

    use JsonApiHttp\RelationshipsController;
    use JsonApiHttp\Request;

    class DoctorRelationshipsController extends RelationshipsController
    {
        /**
         * The resource type.
         *
         * @var string
         */
        protected $type = 'doctors';

        /**
         * Fetch a resource or collection of resources for a relationship.
         *
         * @param \JsonApiHttp\Request $request
         * @param \App\Doctor $doctor
         * @param string $relation
         * @return \Illuminate\Http\Response
         */
        public function index(Request $request, Doctor $doctor, $relation)
        {
            $payload = $this->relations($request, $doctor, $relation);
            return response($payload);
        }

        /**
         * Display a relationship.
         *
         * @param \JsonApiHttp\Request $request
         * @param \App\Doctor $doctor
         * @param string $relation
         * @return \Illuminate\Http\Response
         */
        public function show(Request $request, Doctor $doctor, $relation)
        {
            $relationship = $this->relationship($request, $doctor, $relation);
            return response($relationship);
        }
    }

Create the `patients` table.

    php artisan make:migration create_patients_table --create=patients

Add the below to the migration file.

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('doctor_id')->unsigned()->index();
            $table->foreign('doctor_id')->references('id')->on('doctors');
            $table->string('name');
            $table->timestamps();
        });
    }

Create the `Patient` model and `doctor` relationship.

    <?php

    namespace App;

    use JsonApiHttp\AbstractModel;

    class Patient extends AbstractModel
    {
        /**
         * The resource type.
         *
         * @var string
         */
        protected $type = 'patients';

        /**
         * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
         */
        public function doctor()
        {
            return $this->belongsTo(Doctor::class);
        }

        /**
         * Return any the relations to display in the JSON:API output.
         *
         * @return array
         */
        public function relations()
        {
            return ['doctor'];
        }
    }

Create a controller for the relationships.

    <?php

    namespace App\Http\Controllers;

    use App\Patient;

    use JsonApiHttp\RelationshipsController;
    use JsonApiHttp\Request;

    class PatientRelationshipsController extends RelationshipsController
    {
        /**
         * The resource type.
         *
         * @var string
         */
        protected $type = 'patients';

        /**
         * Fetch a resource or collection of resources for a relationship.
         *
         * @param \JsonApiHttp\Request $request
         * @param \App\Patient $patient
         * @param string $relation
         * @return \Illuminate\Http\Response
         */
        public function index(Request $request, Patient $patient, $relation)
        {
            $payload = $this->relations($request, $patient, $relation);
            return response($payload);
        }

        /**
         * Display a relationship.
         *
         * @param \JsonApiHttp\Request $request
         * @param \App\Patient $patient
         * @param string $relation
         * @return \Illuminate\Http\Response
         */
        public function show(Request $request, Patient $patient, $relation)
        {
            $relationship = $this->relationship($request, $patient, $relation);
            return response($relationship);
        }
    }


Create routes to direct requests to two relationship controllers.

    Route::group(['middleware' => ['jsonapi', 'relationships']], function () {

        Route::get(
            'doctors/{doctor}/{relation}',
            'DoctorRelationshipsController@index'
        )
        ->where('relation', 'patients')
        ->name('doctors.relations.index');

        Route::get(
            'doctors/{doctor}/relationships/{relation}',
            'DoctorRelationshipsController@show'
        )
        ->where('relation', 'patients')
        ->name('doctors.relations.show');

        Route::get(
            'patients/{patient}/{relation}',
            'PatientRelationshipsController@index'
        )
        ->where('relation', 'doctor')
        ->name('patients.relations.index');

        Route::get(
            'patients/{patient}/relationships/{relation}',
            'PatientRelationshipsController@show'
        )
        ->where('relation', 'doctor')
        ->name('patients.relations.show');

    });

The relationships are now accessible on the following endpoints:

* `GET /doctors/:id/patients`
* `GET /doctors/:id/relationships/patients`
* `GET /patients/:id/doctor`
* `GET /patients/:id/relationships/doctor`

### Includes

To fetch the related objects in a single request use the `?include` query 
parameter.

    GET /patients/1?include=doctor

You can also use the dot notation to return the relations of relations.

    GET /patients/1?include=doctor.patients

### Filtering

To filter a result set pass a `filter` parameter in the query string.

The value can be a combination of comma separated values (AND) and/or pipe 
separated values (OR).

Nesting of AND and OR operations can be achieved using parathesis.

The attribute and attribute/value pair to query on are joined by a colon 
character.

Operators supported include `!` (not equal to), `< <= => > <>` (less and 
greater than operators including an alternative not equal to operator), `^ !^` 
(begins/does not begin with), `$ !$` (ends/does not end with), `^$ !^$` 
(contains/does not contain).

To filter by a single attribute (exact value).

    GET /patients?filter=name:tom

To filter by multiple values on a single attribute.

    GET /patients?filter=name:(tom|ben)

Relationships can be queried with the value being one or more IDs of the 
related resources to query on. Both belongs to and has many type relationships 
are supported.

    GET /patients?filter=doctor:1

### Pagination

Use the `page` and `perPage` query parameters to control the paging of result 
sets.

    GET /patients?page=1&perPage=10

### Sorting

Use the `sort` query parameter to sort a result set. The value should be an 
attribute of the model. Prefix with `-` sign to sort descending. Multiple 
attributes can be specified, separated by a comma.

    GET /patients?sort=-name
