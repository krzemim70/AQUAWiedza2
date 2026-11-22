<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ApprovePhotos
{
    public function __construct(private SavePhoto $save) {}

    /** @param list<int> $ids */
    public function handle(User $actor, array $ids): void
    {
        Gate::forUser($actor)->authorize('manage-knowledge');

        DB::transaction(function () use ($actor, $ids): void {
            $photos = Photo::query()->whereKey($ids)->where('verified', false)
                ->orderBy('id')->lockForUpdate()->with('record')->get();

            foreach ($photos as $photo) {
                $this->save->handle($actor, $photo->record, [
                    'url' => $photo->url,
                    'file_path' => $photo->file_path,
                    'verified' => true,
                ], $photo);
            }
        });
    }
}
