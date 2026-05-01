<?php

use Denmarty\MartyQueryFilter\FilterPipeline;
use Denmarty\MartyQueryFilter\QueryFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

it('skips unknown filter keys', function (): void {
    $pipeline = new FilterPipeline([
        'mileage' => PipelineMileageFilter::class,
    ]);

    $query = PipelineLot::query();
    $originalSql = $query->toSql();

    $pipeline->apply($query, ['unknown_filter' => 1000]);

    expect($query->toSql())->toBe($originalSql);
    expect($query->getBindings())->toBe([]);
});

it('fails on duplicate normalized filter keys', function (): void {
    expect(fn (): FilterPipeline => new FilterPipeline([
        'mileage' => PipelineMileageFilter::class,
        'Mileage' => PipelineMileageFilter::class,
    ]))->toThrow(InvalidArgumentException::class, 'Duplicate filter key "mileage"');
});

it('fails when filter does not implement query filter contract', function (): void {
    expect(fn (): FilterPipeline => new FilterPipeline([
        'invalid' => stdClass::class,
    ]))->toThrow(InvalidArgumentException::class, 'must exist and implement');
});

it('resolves filters via laravel container', function (): void {
    app()->bind(PipelineContainerFilter::class, function (): PipelineContainerFilter {
        return new PipelineContainerFilter('custom_injected_field');
    });

    $pipeline = new FilterPipeline([
        'injected' => PipelineContainerFilter::class,
    ]);

    $query = PipelineLot::query();

    $pipeline->apply($query, ['injected' => 'SEDAN']);

    expect($query->toSql())->toContain('custom_injected_field');
    expect($query->getBindings())->toBe(['SEDAN']);
});

it('applies search through filter registry', function (): void {
    $pipeline = new FilterPipeline([
        'mileage' => PipelineMileageFilter::class,
    ]);

    $query = PipelineLot::query();

    $pipeline->apply(
        $query,
        ['search' => 'elantra'],
        [
            'search' => [
                'fields' => ['title'],
                'relations' => [],
            ],
        ],
    );

    expect($query->toSql())->toContain('title');
    expect($query->toSql())->toContain('ilike');
    expect($query->getBindings())->toBe(['%elantra%']);
});

it('runtime filter parameters override constructor parameters', function (): void {
    $pipeline = new FilterPipeline(
        filters: [
            'mileage' => PipelineMileageFilter::class,
        ],
        filterParameters: [
            'search' => [
                'fields' => ['lot_number'],
                'relations' => [],
            ],
        ],
    );

    $query = PipelineLot::query();

    $pipeline->apply(
        $query,
        ['search' => 'elantra'],
        [
            'search' => [
                'fields' => ['title'],
                'relations' => [],
            ],
        ],
    );

    expect($query->toSql())->toContain('title');
    expect($query->toSql())->not->toContain('lot_number');
    expect($query->getBindings())->toBe(['%elantra%']);
});

it('fails when filter parameters contain unknown key', function (): void {
    expect(fn (): FilterPipeline => new FilterPipeline(
        filters: [
            'mileage' => PipelineMileageFilter::class,
        ],
        filterParameters: [
            'unknown_filter' => ['fields' => ['title']],
        ],
    ))->toThrow(InvalidArgumentException::class, 'is not registered in filter registry');
});

it('applies filter through configured relation', function (): void {
    $pipeline = new FilterPipeline(
        filters: [
            'auction_name' => PipelineAuctionNameFilter::class,
        ],
        filterParameters: [
            'auction_name' => [
                'relation' => 'libAuctionName',
            ],
        ],
    );

    $query = PipelineLot::query();

    $pipeline->apply($query, ['auction_name' => 'windmotors']);

    expect($query->toSql())->toContain('exists');
    expect($query->toSql())->toContain('lib_auction_names');
    expect($query->toSql())->toContain('auction_name');
    expect($query->getBindings())->toBe(['windmotors']);
});

it('fails when relation parameter is not valid string', function (): void {
    expect(fn (): FilterPipeline => new FilterPipeline(
        filters: [
            'auction_name' => PipelineAuctionNameFilter::class,
        ],
        filterParameters: [
            'auction_name' => [
                'relation' => [],
            ],
        ],
    ))->toThrow(InvalidArgumentException::class, 'must be a non-empty string');
});

class PipelineLot extends Model
{
    protected $table = 'lots';

    public $timestamps = false;

    public function libAuctionName(): BelongsTo
    {
        return $this->belongsTo(PipelineLibAuctionName::class, 'lib_auction_name_id');
    }
}

class PipelineMileageFilter implements QueryFilter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        return $query->where('mileage', '=', $value);
    }
}

class PipelineContainerFilter implements QueryFilter
{
    public function __construct(private readonly string $column) {}

    public function apply(Builder $query, mixed $value): Builder
    {
        return $query->where($this->column, '=', $value);
    }
}

class PipelineAuctionNameFilter implements QueryFilter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        return $query->where('auction_name', '=', $value);
    }
}

class PipelineLibAuctionName extends Model
{
    protected $table = 'lib_auction_names';

    public $timestamps = false;
}
