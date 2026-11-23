<?php

namespace App\Domain\Shops\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Models\RecordWatch;
use Illuminate\Pagination\LengthAwarePaginator;

class ListRecordWatches
{
    public function __construct(private Catalog $catalog) {}

    /** @return LengthAwarePaginator<int, array{watch: RecordWatch, name: ?string}> */
    public function handle(int $userId, int $page): LengthAwarePaginator
    {
        $watches = RecordWatch::where('user_id', $userId)->whereNull('removed_at')
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25, page: $page);
        $names = [];
        foreach ($watches->getCollection()->groupBy(fn (RecordWatch $watch) => $watch->area->value) as $area => $group) {
            $names[$area] = $this->catalog->labels(Area::from($area), $group->pluck('legacy_id')->all());
        }

        return $watches->through(fn (RecordWatch $watch) => [
            'watch' => $watch,
            'name' => $names[$watch->area->value][$watch->legacy_id] ?? null,
        ]);
    }
}
