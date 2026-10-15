<?php

namespace App\Models;

use App\Enums\PointReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PointTransaction extends Model
{
    protected $fillable = ['kid_id', 'delta', 'reason', 'source_type', 'source_id', 'note'];

    protected function casts(): array
    {
        return [
            'delta' => 'integer',
            'reason' => PointReason::class,
        ];
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kid_id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
