<?php

namespace App\Services;

use App\Enums\PointReason;
use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Every points change is a ledger row; users.points_balance is the cached sum.
 */
class PointsService
{
    public function record(User $kid, int $delta, PointReason $reason, ?Model $source = null, ?string $note = null): PointTransaction
    {
        return DB::transaction(function () use ($kid, $delta, $reason, $source, $note) {
            $transaction = $kid->pointTransactions()->create([
                'delta' => $delta,
                'reason' => $reason,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'note' => $note,
            ]);

            $kid->increment('points_balance', $delta);

            return $transaction;
        });
    }
}
