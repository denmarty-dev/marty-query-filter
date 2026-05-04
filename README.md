# MartyQueryFilter

Reusable query filter pipeline for Laravel Eloquent builders.

## Features

- simple `QueryFilter` contract for custom filters
- `FilterPipeline` with Laravel container resolution
- built-in `search` filter
- relation-aware filters through `whereHas(...)`
- normalized filter keys through `Str::snake(...)`
- support for inline filter configuration and legacy parameter registry

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require denmarty/marty-query-filter
```

Laravel package discovery is supported automatically.

## Core idea

You register allowed filters once, then pass request data into the pipeline.

Each filter:

- has a public input key such as `status`, `auction_name`, `year_of_release`
- points to a class that implements `QueryFilter`
- can optionally receive extra constructor parameters
- can optionally be applied inside a relation

## QueryFilter contract

Every custom filter must implement `Denmarty\MartyQueryFilter\QueryFilter`.

```php
<?php

namespace App\QueryFilters;

use Denmarty\MartyQueryFilter\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

final class StatusFilter implements QueryFilter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        return $query->where(
            column: 'status',
            operator: '=',
            value: $value,
        );
    }
}
```

## Basic usage

```php
<?php

use App\Models\Post;
use App\QueryFilters\StatusFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'status' => [
            'filter' => StatusFilter::class,
        ],
    ],
);

$fields = [
    'status' => 'published',
];

$query = $filters->apply(
    query: Post::query(),
    fields: $fields,
);
```

## Preferred filter configuration format

The recommended format is to declare every filter as a configuration array:

```php
<?php

use App\QueryFilters\AuctionNameFilter;
use App\QueryFilters\MileageFilter;
use App\QueryFilters\YearOfReleaseFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'mileage' => [
            'filter' => MileageFilter::class,
        ],
        'auction_name' => [
            'filter' => AuctionNameFilter::class,
            'relation' => 'libAuctionName',
        ],
        'year_of_release' => [
            'filter' => YearOfReleaseFilter::class,
        ],
    ],
);
```

This format is explicit and keeps all filter metadata in one place.

## Supported constructor arguments

`FilterPipeline` constructor accepts these named arguments:

- `filters`
- `filterParameters`
- `search`

### 1. `filters`

Main filter registry.

Each filter must be declared as a configuration array with the `filter` key:

```php
<?php

use App\QueryFilters\StatusFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'status' => [
            'filter' => StatusFilter::class,
        ],
    ],
);
```

Or with a relation:

```php
<?php

use App\QueryFilters\AuctionNameFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'auction_name' => [
            'filter' => AuctionNameFilter::class,
            'relation' => 'libAuctionName',
        ],
    ],
);
```

### 2. `filterParameters`

Legacy-compatible parameter registry. Still supported.

Use it when you want to keep filter class registration separate from extra parameters:

```php
<?php

use App\QueryFilters\AuctionNameFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'auction_name' => [
            'filter' => AuctionNameFilter::class,
        ],
    ],
    filterParameters: [
        'auction_name' => [
            'relation' => 'libAuctionName',
        ],
    ],
);
```

### 3. `search`

Shortcut for configuring the built-in `search` filter directly in the constructor.

```php
<?php

use App\QueryFilters\StatusFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'status' => [
            'filter' => StatusFilter::class,
        ],
    ],
    search: [
        'fields' => ['title', 'slug'],
        'relations' => [
            'author' => ['name'],
            'category' => ['name'],
        ],
    ],
);
```

Aliases are also supported:

- `search_fields` -> `fields`
- `search_relations` -> `relations`

Example:

```php
<?php

use App\QueryFilters\StatusFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'status' => [
            'filter' => StatusFilter::class,
        ],
    ],
    search: [
        'search_relations' => [
            'author' => ['name'],
        ],
    ],
);
```

## Full example

```php
<?php

use App\Models\Post;
use App\QueryFilters\AuthorNameFilter;
use App\QueryFilters\PublishedFilter;
use App\QueryFilters\StatusFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'status' => [
            'filter' => StatusFilter::class,
        ],
        'published' => [
            'filter' => PublishedFilter::class,
        ],
        'author_name' => [
            'filter' => AuthorNameFilter::class,
            'relation' => 'author',
        ],
    ],
    search: [
        'fields' => ['title', 'slug'],
        'relations' => [
            'author' => ['name'],
            'category' => ['name'],
        ],
    ],
);

$fields = [
    'status' => 'published',
    'author_name' => 'Denis',
    'search' => 'Laravel',
];

$query = $filters->apply(
    query: Post::query(),
    fields: $fields,
);
```

## Relation-aware filters

If a filter configuration contains `relation`, the pipeline applies that filter through `whereHas(...)`.

```php
<?php

use App\QueryFilters\AuctionNameFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'auction_name' => [
            'filter' => AuctionNameFilter::class,
            'relation' => 'libAuctionName',
        ],
    ],
);
```

This produces logic equivalent to:

```php
<?php

use App\QueryFilters\AuctionNameFilter;
use Illuminate\Database\Eloquent\Builder;

$query->whereHas('libAuctionName', function (Builder $relationQuery) use ($value): void {
    (new AuctionNameFilter())->apply(
        query: $relationQuery,
        value: $value,
    );
});
```

Inside the filter, you work with the relation query builder, not the root model query.

## Built-in search filter

`FilterPipeline` automatically registers `search`.

That means you do not need to add `search => SearchFilter::class` manually.

Supported search configuration:

```php
<?php

use App\QueryFilters\StatusFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$filters = new FilterPipeline(
    filters: [
        'status' => [
            'filter' => StatusFilter::class,
        ],
    ],
    search: [
        'fields' => ['title', 'slug'],
        'relations' => [
            'author' => ['name'],
            'author.profile' => ['first_name', 'last_name'],
            'category' => ['name'],
        ],
    ],
);
```

### Search behavior

- empty search value is ignored
- if both `fields` and `relations` are empty, search is ignored
- relation search uses `orWhereHas(...)`
- relation entries with an empty field list are skipped

### Important SQL note

The built-in `SearchFilter` uses `ilike`:

```php
<?php

$query->where(
    column: 'column',
    operator: 'ilike',
    value: '%term%',
);
```

This is appropriate for PostgreSQL.

If your project uses MySQL or another database that does not support `ilike`, create your own search filter or override the package behavior for that project.

## Container resolution

Filters are resolved through the Laravel container:

- `app()->make(...)`
- `app()->makeWith(...)`

So constructor injection is supported.

```php
<?php

namespace App\QueryFilters;

use Denmarty\MartyQueryFilter\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

final class VisibilityFilter implements QueryFilter
{
    public function __construct(
        private readonly string $column = 'visibility',
    ) {}

    public function apply(Builder $query, mixed $value): Builder
    {
        return $query->where(
            column: $this->column,
            operator: '=',
            value: $value,
        );
    }
}
```

## Filter key normalization

Filter keys are normalized with `Str::snake(...)`.

That means these keys resolve to the same filter:

- `auction_name`
- `AuctionName`
- `auctionName`

Because of that, duplicate normalized keys are not allowed.

## Validation rules enforced by the pipeline

The pipeline throws `InvalidArgumentException` when:

- a filter key is empty
- the same normalized filter key is registered twice
- a filter class does not exist
- a filter class does not implement `QueryFilter`
- inline filter configuration does not contain a valid `filter` key
- `relation` is present but not a non-empty string
- `filterParameters` contains a key that is not registered in `filters`

## Unknown input keys

Unknown keys from request input are ignored.

```php
<?php

use App\Models\Post;
use App\QueryFilters\StatusFilter;
use Denmarty\MartyQueryFilter\FilterPipeline;

$pipeline = new FilterPipeline(
    filters: [
        'status' => [
            'filter' => StatusFilter::class,
        ],
    ],
);

$query = $pipeline->apply(
    query: Post::query(),
    fields: [
        'status' => 'published',
        'unknown_filter' => 'value',
    ],
);
```

Only `status` will be applied.

## Package structure

- `src/QueryFilter.php` - filter contract
- `src/FilterPipeline.php` - registry, validation, execution
- `src/SearchFilter.php` - built-in text search filter

## Testing

```bash
composer test
```

## Formatting

```bash
composer lint
```

## License

MIT
