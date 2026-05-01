<?php

namespace Denmarty\MartyQueryFilter;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use InvalidArgumentException;

class FilterPipeline
{
    /**
     * @var array<string, class-string<QueryFilter>>
     */
    protected array $filters;

    /**
     * @var array<string, array<string, mixed>>
     */
    protected array $filterParameters;

    /**
     * @param  array<string, class-string<QueryFilter>>  $filters
     * @param  array<string, array<string, mixed>>  $filterParameters
     */
    public function __construct(array $filters, array $filterParameters = [])
    {
        $this->filters = $this->normalizeFilters([
            'search' => SearchFilter::class,
            ...$filters,
        ]);

        $this->filterParameters = $this->normalizeFilterParameters($filterParameters);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, array<string, mixed>> $runtimeFilterParameters
     *
     * @throws BindingResolutionException
     */
    public function apply(Builder $query, array $fields, array $runtimeFilterParameters = []): Builder
    {
        $resolvedFilterParameters = [
            ...$this->filterParameters,
            ...$this->normalizeFilterParameters($runtimeFilterParameters),
        ];

        foreach ($fields as $field => $value) {
            $normalizedField = $this->normalizeFilterKey($field);
            if ($normalizedField === null) {
                continue;
            }

            $filterClass = $this->filters[$normalizedField] ?? null;
            if ($filterClass === null) {
                continue;
            }

            $filterParameters = $resolvedFilterParameters[$normalizedField] ?? [];
            $relation = $this->extractRelation($filterParameters);
            $filter = $this->resolveFilter($filterClass, $filterParameters);

            $query = $this->applyFilter($query, $filter, $value, $relation);
        }

        return $query;
    }

    /**
     * @param class-string<QueryFilter> $filterClass
     * @param array<string, mixed> $parameters
     *
     * @throws BindingResolutionException
     */
    protected function resolveFilter(string $filterClass, array $parameters): QueryFilter
    {
        $filter = $parameters === []
            ? app()->make($filterClass)
            : app()->makeWith($filterClass, $parameters);

        if (! $filter instanceof QueryFilter) {
            throw new InvalidArgumentException(sprintf(
                'Resolved filter "%s" must implement %s.',
                $filterClass,
                QueryFilter::class
            ));
        }

        return $filter;
    }

    protected function applyFilter(Builder $query, QueryFilter $filter, mixed $value, ?string $relation): Builder
    {
        if ($relation === null) {
            return $filter->apply($query, $value);
        }

        return $query->whereHas($relation, function (Builder $relationQuery) use ($filter, $value): void {
            $filter->apply($relationQuery, $value);
        });
    }

    /**
     * @param  array<string, class-string<QueryFilter>>  $filters
     * @return array<string, class-string<QueryFilter>>
     */
    protected function normalizeFilters(array $filters): array
    {
        $registry = [];

        foreach ($filters as $field => $filterClass) {
            if (! is_string($field)) {
                throw new InvalidArgumentException(
                    'Filter registry must use explicit string keys (normalized filter keys).'
                );
            }

            if (! is_string($filterClass)) {
                throw new InvalidArgumentException('Filter class name must be a string.');
            }

            $filterField = $this->normalizeFilterKey($field);
            if ($filterField === null) {
                throw new InvalidArgumentException('Filter key must be a non-empty string.');
            }

            if (! class_exists($filterClass) || ! is_subclass_of($filterClass, QueryFilter::class)) {
                throw new InvalidArgumentException(sprintf(
                    'Filter "%s" for field "%s" must exist and implement %s.',
                    $filterClass,
                    $filterField,
                    QueryFilter::class
                ));
            }

            if (isset($registry[$filterField])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate filter key "%s" in filter registry.',
                    $filterField
                ));
            }

            $registry[$filterField] = $filterClass;
        }

        return $registry;
    }

    /**
     * @param  array<string, array<string, mixed>>  $filterParameters
     * @return array<string, array<string, mixed>>
     */
    protected function normalizeFilterParameters(array $filterParameters): array
    {
        $registry = [];

        foreach ($filterParameters as $field => $parameters) {
            if (! is_string($field)) {
                throw new InvalidArgumentException(
                    'Filter parameter registry must use explicit string keys (normalized filter keys).'
                );
            }

            $filterField = $this->normalizeFilterKey($field);
            if ($filterField === null) {
                throw new InvalidArgumentException('Filter parameter key must be a non-empty string.');
            }

            if (! isset($this->filters[$filterField])) {
                throw new InvalidArgumentException(sprintf(
                    'Filter parameter key "%s" is not registered in filter registry.',
                    $filterField
                ));
            }

            if (! is_array($parameters)) {
                throw new InvalidArgumentException(sprintf(
                    'Filter parameters for "%s" must be an array.',
                    $filterField
                ));
            }

            if (array_key_exists('relation', $parameters)
                && (! is_string($parameters['relation']) || trim($parameters['relation']) === '')
            ) {
                throw new InvalidArgumentException(sprintf(
                    'Filter relation for "%s" must be a non-empty string.',
                    $filterField
                ));
            }

            if (isset($registry[$filterField])) {
                throw new InvalidArgumentException(sprintf(
                    'Duplicate filter parameter key "%s" in parameter registry.',
                    $filterField
                ));
            }

            $registry[$filterField] = $parameters;
        }

        return $registry;
    }

    /**
     * @param  array<string, mixed>  $filterParameters
     */
    protected function extractRelation(array &$filterParameters): ?string
    {
        if (! array_key_exists('relation', $filterParameters)) {
            return null;
        }

        $relation = trim((string) $filterParameters['relation']);
        unset($filterParameters['relation']);

        return $relation;
    }

    protected function normalizeFilterKey(mixed $field): ?string
    {
        if (! is_string($field) && ! is_int($field)) {
            return null;
        }

        $normalized = Str::snake(trim((string) $field));

        return $normalized !== '' ? $normalized : null;
    }
}
