<?php

namespace Denmarty\MartyQueryFilter;

use Illuminate\Database\Eloquent\Builder;

interface QueryFilter
{
    public function apply(Builder $query, mixed $value): Builder;
}
