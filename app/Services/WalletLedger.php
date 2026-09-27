<?php

namespace App\Services;

use App\Enums\MilestoneStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\EmployerSubscription;
use App\Models\Milestone;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Support\PersianText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every money movement goes through here, inside a database transaction with locked wallets.
 *
 * Ledger convention: a row's amount is its effect on the wallet's *available* balance, so
 * for every wallet: balance = sum of its succeeded transactions. Escrow holds move money from
 * balance to held_balance (negative row); a release pays the freelancer (positive row) and
 * charges the platform fee on the freelancer's wallet (negative row).
 */
class WalletLedger
{
    /**
     * Credit the wallet through the sandbox gateway (development and testing only).
     */
    public function deposit(User $user, int $amount): Transaction
    {
        return DB::transaction(function () use ($user, $amount): Transaction {
            $wallet = $this->lockWalletOf($user);
            $wallet->increment('balance', $amount);

            return $wallet->transactions()->create([
                'type' => TransactionType::Deposit,
                'amount' => $amount,
                'status' => TransactionStatus::Succeeded,
                'gateway' => 'sandbox',
                'gateway_ref' => 'SBX-'.Str::upper(Str::random(12)),
                'description' => __('Wallet top-up (sandbox)'),
            ]);
        });
    }

    /**
     * Move the milestone amount from the employer's balance into escrow.
     *
     * @throws ValidationException
     */
    public function fundMilestone(Milestone $milestone): void
    {
        DB::transaction(function () use ($milestone): void {
            $milestone = Milestone::with('contract')->lockForUpdate()->findOrFail($milestone->id);

            if ($milestone->status !== MilestoneStatus::Pending) {
                throw ValidationException::withMessages(['milestone' => __('This milestone is already funded.')]);
            }

            $wallet = $this->lockWalletOf($milestone->contract->employer_id);

            if ($wallet->balance < $milestone->amount) {
                throw ValidationException::withMessages(['milestone' => __('Your wallet balance is not enough. Top up :amount Toman first.', [
                    'amount' => PersianText::number($milestone->amount - $wallet->balance),
                ])]);
            }

            $wallet->decrement('balance', $milestone->amount);
            $wallet->increment('held_balance', $milestone->amount);

            $wallet->transactions()->create([
                'contract_id' => $milestone->contract_id,
                'milestone_id' => $milestone->id,
                'type' => TransactionType::EscrowHold,
                'amount' => -$milestone->amount,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Escrow for milestone: :title', ['title' => $milestone->title]),
            ]);

            $milestone->update(['status' => MilestoneStatus::Funded]);
        });
    }

    /**
     * Pay a submitted milestone out of escrow to the freelancer, minus the platform fee.
     *
     * @throws ValidationException
     */
    public function releaseMilestone(Milestone $milestone): void
    {
        DB::transaction(function () use ($milestone): void {
            $milestone = Milestone::with('contract')->lockForUpdate()->findOrFail($milestone->id);

            if (! in_array($milestone->status, [MilestoneStatus::Submitted, MilestoneStatus::Approved], true)) {
                throw ValidationException::withMessages(['milestone' => __('Only delivered milestones can be released.')]);
            }

            $contract = $milestone->contract;
            $employerWallet = $this->lockWalletOf($contract->employer_id);
            $freelancerWallet = $this->lockWalletOf($contract->freelancer_id);
            $fee = intdiv($milestone->amount * $contract->fee_percent, 100);

            $employerWallet->decrement('held_balance', min($employerWallet->held_balance, $milestone->amount));
            $freelancerWallet->increment('balance', $milestone->amount - $fee);

            $freelancerWallet->transactions()->create([
                'contract_id' => $contract->id,
                'milestone_id' => $milestone->id,
                'type' => TransactionType::EscrowRelease,
                'amount' => $milestone->amount,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Payment for milestone: :title', ['title' => $milestone->title]),
            ]);

            $freelancerWallet->transactions()->create([
                'contract_id' => $contract->id,
                'milestone_id' => $milestone->id,
                'type' => TransactionType::Fee,
                'amount' => -$fee,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Platform fee (:percent%)', ['percent' => PersianText::number($contract->fee_percent)]),
            ]);

            $milestone->update(['status' => MilestoneStatus::Released]);
        });
    }

    /**
     * Buy a plan from the employer's wallet and start the subscription.
     *
     * @throws ValidationException
     */
    public function purchasePlan(User $employer, Plan $plan): EmployerSubscription
    {
        return DB::transaction(function () use ($employer, $plan): EmployerSubscription {
            $wallet = $this->lockWalletOf($employer);

            if ($wallet->balance < $plan->price) {
                throw ValidationException::withMessages(['plan' => __('Your wallet balance is not enough. Top up :amount Toman first.', [
                    'amount' => PersianText::number($plan->price - $wallet->balance),
                ])]);
            }

            $employer->subscriptions()
                ->where('status', SubscriptionStatus::Active)
                ->update(['status' => SubscriptionStatus::Cancelled]);

            $subscription = $employer->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'started_at' => now(),
                'expires_at' => $plan->duration_days ? now()->addDays($plan->duration_days) : null,
            ]);

            $wallet->decrement('balance', $plan->price);

            $wallet->transactions()->create([
                'subscription_id' => $subscription->id,
                'type' => TransactionType::PlanPurchase,
                'amount' => -$plan->price,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Plan purchase: :name', ['name' => $plan->name]),
            ]);

            return $subscription;
        });
    }

    /**
     * The user's wallet (created on first use), locked for the rest of the transaction.
     */
    private function lockWalletOf(User|int $user): Wallet
    {
        $user = $user instanceof User ? $user : User::withTrashed()->findOrFail($user);

        $user->wallet()->firstOrCreate();

        return Wallet::whereBelongsTo($user)->lockForUpdate()->firstOrFail();
    }
}
