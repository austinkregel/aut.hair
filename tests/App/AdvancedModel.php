<?php

declare(strict_types=1);

namespace Tests\App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvancedModel extends Model
{
    public function basicMode(): BelongsTo
    {
        return $this->belongsTo(BasicModel::class);
    }
}
