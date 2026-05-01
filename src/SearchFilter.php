<?php

namespace Denmarty\MartyQueryFilter;

use Illuminate\Database\Eloquent\Builder;

class SearchFilter implements QueryFilter
{
    /**
     * @var list<string>
     */
    protected array $fields;

    /**
     * @var array<string, list<string>>
     */
    protected array $relations;

    /**
     * @param list<string> $fields
     * @param array<string, list<string>> $relations
     */
    public function __construct(array $fields = [], array $relations = [])
    {
        $this->fields = $fields;
        $this->relations = $relations;
    }

    /**
     * @param list<string> $fields
     * @param array<string, list<string>> $relations
     */
    public function setSearchConfiguration(array $fields = [], array $relations = []): self
    {
        $this->fields = $fields;
        $this->relations = $relations;

        return $this;
    }

    public function apply(Builder $query, mixed $value): Builder
    {
        $searchValue = is_scalar($value) ? trim((string) $value) : '';
        if ($searchValue === '' || ! $this->hasSearchTargets()) {
            return $query;
        }

        $searchTerm = "%{$searchValue}%";

        return $query->where(function (Builder $nestedQuery) use ($searchTerm): void {
            $this->applyFieldSearch($nestedQuery, $searchTerm);
            $this->applyRelationSearch($nestedQuery, $searchTerm);
        });
    }

    private function hasSearchTargets(): bool
    {
        if ($this->fields !== []) {
            return true;
        }

        foreach ($this->relations as $relationFields) {
            if ($relationFields !== []) {
                return true;
            }
        }

        return false;
    }

    private function applyFieldSearch(Builder $query, string $searchTerm): void
    {
        $this->applyLikeConditions($query, $this->fields, $searchTerm, true);
    }

    private function applyRelationSearch(Builder $query, string $searchTerm): void
    {
        foreach ($this->relations as $relation => $relationFields) {
            if ($relationFields === []) {
                continue;
            }

            $query->orWhereHas($relation, function (Builder $relationQuery) use ($relationFields, $searchTerm): void {
                $relationQuery->where(function (Builder $nestedRelationQuery) use ($relationFields, $searchTerm): void {
                    $this->applyLikeConditions($nestedRelationQuery, $relationFields, $searchTerm);
                });
            });
        }
    }

    /**
     * @param list<string> $fields
     */
    private function applyLikeConditions(
        Builder $query,
        array $fields,
        string $searchTerm,
        bool $useOuterOr = false
    ): void {
        foreach ($fields as $index => $field) {
            if ($index === 0) {
                $method = $useOuterOr ? 'orWhere' : 'where';
                $query->{$method}($field, 'ilike', $searchTerm);

                continue;
            }

            $query->orWhere($field, 'ilike', $searchTerm);
        }
    }
}
