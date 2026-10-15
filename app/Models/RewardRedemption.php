<?php

namespace App\Models;

use App\Enums\RedemptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    protected $fillable = ['reward_id', 'kid_id', 'cost', 'status', 'handled_by', 'handled_at'];

    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'status' => RedemptionStatus::class,
            'handled_at' => 'datetime',
        ];
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function kid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'kid_id');
    }
}
