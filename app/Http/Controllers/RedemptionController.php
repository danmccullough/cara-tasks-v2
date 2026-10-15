<?php

namespace App\Http\Controllers;

use App\Enums\PointReason;
use App\Enums\RedemptionStatus;
use App\Models\Reward;
use App\Models\RewardRedemption;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RedemptionController extends Controller
{
    public function __construct(private PointsService $points) {}

    /** Kid's reward shop. */
    public function shop(Request $request): Response
    {
        return Inertia::render('kid/Rewards', [
            'rewards' => Reward::where('active', true)->orderBy('cost')->get(['id', 'title', 'icon', 'cost']),
            'points' => $request->user()->points_balance,
            'requested' => $request->user()->redemptions()->with('reward:id,title,icon')
                ->where('status', RedemptionStatus::Requested)->latest()->get(),
        ]);
    }

    /** Kid spends points now; a parent fulfills or denies (refund) later. */
    public function redeem(Request $request, Reward $reward): RedirectResponse
    {
        abort_unless($reward->active, 404);

        DB::transaction(function () use ($request, $reward) {
            $kid = User::lockForUpdate()->findOrFail($request->user()->id);

            if ($kid->points_balance < $reward->cost) {
                throw ValidationException::withMessages(['points' => 'Not enough points yet. Keep going!']);
            }

            $redemption = $kid->redemptions()->create([
                'reward_id' => $reward->id,
                'cost' => $reward->cost,
                'status' => RedemptionStatus::Requested,
            ]);

            $this->points->record($kid, -$reward->cost, PointReason::Reward, $redemption);
        });

        return back();
    }

    public function fulfill(Request $request, RewardRedemption $redemption): RedirectResponse
    {
        $this->handle($request, $redemption, RedemptionStatus::Fulfilled);

        return back();
    }

    public function deny(Request $request, RewardRedemption $redemption): RedirectResponse
    {
        $this->handle($request, $redemption, RedemptionStatus::Denied);

        return back();
    }

    private function handle(Request $request, RewardRedemption $redemption, RedemptionStatus $status): void
    {
        DB::transaction(function () use ($request, $redemption, $status) {
            $redemption = RewardRedemption::lockForUpdate()->findOrFail($redemption->id);
            abort_unless($redemption->status === RedemptionStatus::Requested, 409);

            $redemption->update([
                'status' => $status,
                'handled_by' => $request->user()->id,
                'handled_at' => now(),
            ]);

            if ($status === RedemptionStatus::Denied) {
                $this->points->record($redemption->kid, $redemption->cost, PointReason::Reward, $redemption, 'Refund');
            }
        });
    }
}
