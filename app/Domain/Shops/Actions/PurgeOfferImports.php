<?php

namespace App\Domain\Shops\Actions;

use App\Domain\Shops\Models\ImportBatch;
use Illuminate\Support\Facades\DB;

class PurgeOfferImports
{
    public function handle(): void
    {
        ImportBatch::where('expires_at', '<=', now())->select('id')->chunkById(100, function ($batches) {
            foreach ($batches as $batch) {
                DB::transaction(function () use ($batch) {
                    $expired = ImportBatch::where('expires_at', '<=', now())->lockForUpdate()->find($batch->id);
                    if ($expired !== null) {
                        $expired->deleteFile();
                        if ($expired->file_path === null) {
                            $expired->delete();
                        }
                    }
                });
            }
        });
    }
}
