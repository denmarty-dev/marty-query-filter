<?php

use Denmarty\MartyQueryFilter\SearchFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

it('skips search when input is empty', function (): void {
    $filter = new SearchFilter(['title'], []);
    $query = SearchLot::query();
    $originalSql = $query->toSql();

    $filter->apply($query, '   ');

    expect($query->toSql())->toBe($originalSql);
    expect($query->getBindings())->toBe([]);
});

it('applies search across configured fields', function (): void {
    $filter = new SearchFilter(['title', 'lot_number'], []);
    $query = SearchLot::query();

    $filter->apply($query, 'sonata');

    expect($query->toSql())->toContain('title');
    expect($query->toSql())->toContain('lot_number');
    expect($query->toSql())->toContain('ilike');
    expect($query->getBindings())->toBe(['%sonata%', '%sonata%']);
});

it('applies search across relation fields', function (): void {
    $filter = new SearchFilter([], [
        'auctionType' => ['name'],
    ]);
    $query = SearchLot::query();

    $filter->apply($query, 'kia');

    expect($query->toSql())->toContain('exists');
    expect($query->toSql())->toContain('auction_types');
    expect($query->toSql())->toContain('name');
    expect($query->getBindings())->toBe(['%kia%']);
});

it('keeps relation key constraint and search predicate in separate groups', function (): void {
    $filter = new SearchFilter([], [
        'auctionType' => ['name'],
    ]);
    $query = SearchLot::query();

    $filter->apply($query, 'kia');

    $sql = $query->toSql();

    expect($sql)->toContain('"lots"."auction_type_id" = "auction_types"."id" and (');
    expect($sql)->toContain('"name" ilike ?');
    expect($sql)->not->toContain('"lots"."auction_type_id" = "auction_types"."id" or');
});

it('skips relation search when relation field list is empty', function (): void {
    $filter = new SearchFilter([], [
        'auctionType' => [],
    ]);
    $query = SearchLot::query();
    $originalSql = $query->toSql();

    $filter->apply($query, 'kia');

    expect($query->toSql())->toBe($originalSql);
    expect($query->getBindings())->toBe([]);
});

class SearchLot extends Model
{
    protected $table = 'lots';

    public $timestamps = false;

    public function auctionType(): BelongsTo
    {
        return $this->belongsTo(SearchAuctionType::class, 'auction_type_id');
    }
}

class SearchAuctionType extends Model
{
    protected $table = 'auction_types';

    public $timestamps = false;
}
