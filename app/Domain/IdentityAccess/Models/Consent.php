<?php

namespace App\Domain\IdentityAccess\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'document', 'document_version', 'granted', 'ip', 'created_at'])]
class Consent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['granted' => 'boolean', 'created_at' => 'datetime'];
    }
}
