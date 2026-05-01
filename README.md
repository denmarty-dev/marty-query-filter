# MartyQueryFilter

Reusable query filter pipeline for Laravel Eloquent builders.

## Features

- Small `QueryFilter` contract for custom Eloquent filters
- `FilterPipeline` with normalized keys and container-based filter resolution
- Built-in `search` filter registration
- Relation-aware filtering through `whereHas`
- Laravel package auto-discovery support

## Requirements

- PHP 8.2+
- Laravel 11 or 12

## Installation

```bash
composer require denmarty/marty-query-filter
```

## Usage

```php
use Denmarty\MartyQueryFilter\FilterPipeline;
use Denmarty\MartyQueryFilter\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Post;

final class StatusFilter implements QueryFilter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        return $query->where('status', '=', $value);
    }
}

$pipeline = new FilterPipeline(
    filters: [
        'status' => StatusFilter::class,
    ],
    filterParameters: [
        'search' => [
            'fields' => ['title'],
            'relations' => [
                'author' => ['name'],
            ],
        ],
    ],
);

$query = $pipeline->apply(Post::query(), request()->all());
```

## Creating custom filters

```php
use Denmarty\MartyQueryFilter\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

final class PublishedFilter implements QueryFilter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        if (! $value) {
            return $query;
        }

        return $query->whereNotNull('published_at');
    }
}
```

## Built-in search filter

`FilterPipeline` automatically registers `search` through `SearchFilter::class`.

Supported `search` parameters:

- `fields`: list of columns on the current model
- `relations`: map of relation name to searchable columns

Example:

```php
[
    'search' => [
        'fields' => ['title', 'slug'],
        'relations' => [
            'category' => ['name'],
            'author.profile' => ['first_name', 'last_name'],
        ],
    ],
]
```

## Relation-aware filters

Any filter can be attached to a relation through `filterParameters`:

```php
[
    'auction_name' => [
        'relation' => 'libAuctionName',
    ],
]
```

The filter itself still receives the relation query builder.

## Runtime parameter override

Runtime filter parameters can override constructor parameters:

```php
$pipeline->apply(
    Post::query(),
    ['search' => 'sedan'],
    [
        'search' => [
            'fields' => ['title'],
            'relations' => [
                'category' => ['name'],
            ],
        ],
    ],
);
```

## Package structure

- `src/QueryFilter.php`: filter contract
- `src/FilterPipeline.php`: registry and execution pipeline
- `src/SearchFilter.php`: reusable text search filter

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
