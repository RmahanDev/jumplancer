<?php

namespace App\Services;

use App\Enums\DisputeOutcome;
use App\Enums\MilestoneStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\AssessmentAttempt;
use App\Models\BankCard;
use App\Models\Contract;
use App\Models\EmployerSubscription;
use App\Models\Milestone;
use App\Models\Plan;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Support\PersianText;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Every money movement goes through here, inside a database transaction with locked wallets.
 *
 * Ledger convention: a row's amount is its effect on the wallet's *available* balance, so
 * for every wallet: balance = sum of its succeeded and pending transactions (a pending payout
 * already left the available balance). Escrow holds move money from balance to held_balance
 * (negative row); a release pays the freelancer (positive row) and charges the platform fee on
 * the freelancer's wallet (negative row); a refund moves held money back to balance (positive row).
 *
 * The good-faith deposit taken when hiring is an escrow hold on the contract itself. Milestones
 * spend it first, and whatever is left goes back to the employer when the contract completes, or
 * wherever the expert decides when a dispute is closed.
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
     * Good-faith deposit taken when an employer hires: a share of the contract amount moves from
     * the employer's balance into escrow. Call it inside the transaction that creates the contract.
     *
     * @throws ValidationException
     */
    public function holdHireDeposit(Contract $contract, int $percent): void
    {
        DB::transaction(function () use ($contract, $percent): void {
            $deposit = self::depositFor($contract->amount, $percent);

            if ($deposit === 0) {
                return;
            }

            $wallet = $this->lockWalletOf($contract->employer_id);

            if ($wallet->balance < $deposit) {
                throw ValidationException::withMessages(['deposit' => __('Hiring needs a good-faith deposit of :deposit Toman in your wallet. Top up :amount Toman first.', [
                    'deposit' => PersianText::number($deposit),
                    'amount' => PersianText::number($deposit - $wallet->balance),
                ])]);
            }

            $wallet->decrement('balance', $deposit);
            $wallet->increment('held_balance', $deposit);

            $wallet->transactions()->create([
                'contract_id' => $contract->id,
                'type' => TransactionType::EscrowHold,
                'amount' => -$deposit,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Good-faith deposit (:percent%) for hiring: :title', [
                    'percent' => PersianText::number($percent),
                    'title' => $contract->project->title,
                ]),
            ]);

            $contract->forceFill(['deposit_amount' => $deposit, 'deposit_balance' => $deposit])->save();
        });
    }

    /**
     * The deposit for a price, rounded up to a whole Toman.
     */
    public static function depositFor(int $amount, int $percent): int
    {
        return intdiv($amount * $percent + 99, 100);
    }

    /**
     * Move the milestone amount into escrow: first from the contract's unspent deposit (already
     * held), then the rest from the employer's balance.
     *
     * @throws ValidationException
     */
    public function fundMilestone(Milestone $milestone): void
    {
        DB::transaction(function () use ($milestone): void {
            $milestone = Milestone::lockForUpdate()->findOrFail($milestone->id);

            if ($milestone->status !== MilestoneStatus::Pending) {
                throw ValidationException::withMessages(['milestone' => __('This milestone is already funded.')]);
            }

            $contract = Contract::lockForUpdate()->findOrFail($milestone->contract_id);
            $wallet = $this->lockWalletOf($contract->employer_id);
            $fromDeposit = min($contract->deposit_balance, $milestone->amount);
            $fromBalance = $milestone->amount - $fromDeposit;

            if ($wallet->balance < $fromBalance) {
                throw ValidationException::withMessages(['milestone' => __('Your wallet balance is not enough. Top up :amount Toman first.', [
                    'amount' => PersianText::number($fromBalance - $wallet->balance),
                ])]);
            }

            if ($fromDeposit > 0) {
                $contract->decrement('deposit_balance', $fromDeposit);
            }

            if ($fromBalance > 0) {
                $wallet->decrement('balance', $fromBalance);
                $wallet->increment('held_balance', $fromBalance);
            }

            // The deposit part was already held, so only the new money is a ledger row (amount = effect on balance).
            $wallet->transactions()->create([
                'contract_id' => $milestone->contract_id,
                'milestone_id' => $milestone->id,
                'type' => TransactionType::EscrowHold,
                'amount' => -$fromBalance,
                'status' => TransactionStatus::Succeeded,
                'description' => $fromDeposit > 0
                    ? __('Escrow for milestone: :title (:deposit Toman from the good-faith deposit)', [
                        'title' => $milestone->title,
                        'deposit' => PersianText::number($fromDeposit),
                    ])
                    : __('Escrow for milestone: :title', ['title' => $milestone->title]),
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

            $this->payOutOfEscrow($milestone->contract, $milestone->amount, __('Payment for milestone: :title', ['title' => $milestone->title]), $milestone);

            $milestone->update(['status' => MilestoneStatus::Released]);
        });
    }

    /**
     * Money leaves the employer's escrow for the freelancer: the freelancer is credited the amount
     * and charged the contract's fee; the mentor of the contract (if any) is paid their share of
     * the amount out of that fee, and the rest of the fee is the platform's income.
     */
    private function payOutOfEscrow(Contract $contract, int $amount, string $description, ?Milestone $milestone = null): void
    {
        $employerWallet = $this->lockWalletOf($contract->employer_id);
        $freelancerWallet = $this->lockWalletOf($contract->freelancer_id);
        $fee = self::percentOf($amount, $contract->fee_percent);

        $employerWallet->decrement('held_balance', min($employerWallet->held_balance, $amount));
        $freelancerWallet->increment('balance', $amount - $fee);

        $freelancerWallet->transactions()->create([
            'contract_id' => $contract->id,
            'milestone_id' => $milestone?->id,
            'type' => TransactionType::EscrowRelease,
            'amount' => $amount,
            'status' => TransactionStatus::Succeeded,
            'description' => $description,
        ]);

        $freelancerWallet->transactions()->create([
            'contract_id' => $contract->id,
            'milestone_id' => $milestone?->id,
            'type' => TransactionType::Fee,
            'amount' => -$fee,
            'status' => TransactionStatus::Succeeded,
            'description' => __('Platform fee (:percent%)', ['percent' => PersianText::percent($contract->fee_percent)]),
        ]);

        $share = $contract->mentor_id !== null ? self::percentOf($amount, $contract->mentor_share_percent) : 0;

        if ($share > 0) {
            $mentorWallet = $this->lockWalletOf($contract->mentor_id);
            $mentorWallet->increment('balance', $share);

            $mentorWallet->transactions()->create([
                'contract_id' => $contract->id,
                'milestone_id' => $milestone?->id,
                'type' => TransactionType::MentorPayout,
                'amount' => $share,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Mentor share (:percent%) of: :title', [
                    'percent' => PersianText::percent($contract->mentor_share_percent),
                    'title' => $milestone?->title ?? $contract->project()->value('title'),
                ]),
            ]);
        }
    }

    /**
     * A percentage (up to two decimals) of an amount in whole Toman, rounded down.
     */
    public static function percentOf(int $amount, float $percent): int
    {
        return intdiv($amount * (int) round($percent * 100), 10000);
    }

    /**
     * Give the unspent deposit back to the employer (the contract finished without needing it).
     */
    public function refundDeposit(Contract $contract): int
    {
        return DB::transaction(function () use ($contract): int {
            $contract = Contract::with('project')->lockForUpdate()->findOrFail($contract->id);
            $left = $contract->deposit_balance;

            if ($left === 0) {
                return 0;
            }

            $wallet = $this->lockWalletOf($contract->employer_id);
            $wallet->decrement('held_balance', min($wallet->held_balance, $left));
            $wallet->increment('balance', $left);

            $wallet->transactions()->create([
                'contract_id' => $contract->id,
                'type' => TransactionType::Refund,
                'amount' => $left,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Unused good-faith deposit returned: :title', ['title' => $contract->project->title]),
            ]);

            $contract->update(['deposit_balance' => 0]);

            return $left;
        });
    }

    /**
     * Carry out an expert's decision on everything held for a contract (deposit and funded milestones).
     *
     * @return int the amount that moved
     */
    public function settleDispute(Contract $contract, DisputeOutcome $outcome): int
    {
        if ($outcome === DisputeOutcome::Continue) {
            return 0;
        }

        return DB::transaction(function () use ($contract, $outcome): int {
            $contract = Contract::with('project')->lockForUpdate()->findOrFail($contract->id);
            $held = Milestone::where('contract_id', $contract->id)
                ->whereIn('status', [MilestoneStatus::Funded, MilestoneStatus::Submitted, MilestoneStatus::Approved])
                ->lockForUpdate()
                ->get();

            if ($outcome === DisputeOutcome::PayFreelancer) {
                foreach ($held as $milestone) {
                    $milestone->update(['status' => MilestoneStatus::Submitted]);
                    $this->releaseMilestone($milestone);
                }

                return (int) $held->sum('amount') + $this->payDepositToFreelancer($contract);
            }

            $total = (int) $held->sum('amount') + $contract->deposit_balance;

            if ($total > 0) {
                $wallet = $this->lockWalletOf($contract->employer_id);
                $wallet->decrement('held_balance', min($wallet->held_balance, $total));
                $wallet->increment('balance', $total);

                $wallet->transactions()->create([
                    'contract_id' => $contract->id,
                    'type' => TransactionType::Refund,
                    'amount' => $total,
                    'status' => TransactionStatus::Succeeded,
                    'description' => __('Refund by expert decision: :title', ['title' => $contract->project->title]),
                ]);
            }

            Milestone::whereKey($held->modelKeys())->update(['status' => MilestoneStatus::Refunded]);
            $contract->update(['deposit_balance' => 0]);

            return $total;
        });
    }

    /**
     * Pay the unspent deposit to the freelancer, minus the platform fee.
     */
    private function payDepositToFreelancer(Contract $contract): int
    {
        $amount = $contract->deposit_balance;

        if ($amount === 0) {
            return 0;
        }

        $this->payOutOfEscrow($contract, $amount, __('Good-faith deposit paid by expert decision: :title', ['title' => $contract->project->title]));

        $contract->update(['deposit_balance' => 0]);

        return $amount;
    }

    /**
     * Reserve wallet money for a payout to the user's card. Only the available balance can be
     * withdrawn; money held in escrow (deposits, funded milestones) stays where it is.
     *
     * @throws ValidationException
     */
    public function requestWithdrawal(User $user, BankCard $card, int $amount): WithdrawalRequest
    {
        return DB::transaction(function () use ($user, $card, $amount): WithdrawalRequest {
            $wallet = $this->lockWalletOf($user);

            if ($amount > $wallet->balance) {
                throw ValidationException::withMessages(['amount' => __('You can withdraw up to :amount Toman. Money held in escrow cannot be withdrawn.', [
                    'amount' => PersianText::number($wallet->balance),
                ])]);
            }

            $wallet->decrement('balance', $amount);

            $transaction = $wallet->transactions()->create([
                'type' => TransactionType::Payout,
                'amount' => -$amount,
                'status' => TransactionStatus::Pending,
                // Isolated left-to-right so the digit groups keep their order inside Persian text.
                'description' => __('Withdrawal to card :card', ['card' => "\u{2066}{$card->maskedNumber()}\u{2069}"]),
            ]);

            $request = $user->withdrawalRequests()->make([
                'amount' => $amount,
                'card_number' => $card->card_number,
                'holder_name' => $card->holder_name,
                'bank_name' => $card->bank_name,
                'status' => WithdrawalStatus::Pending,
            ]);
            $request->bankCard()->associate($card);
            $request->transaction()->associate($transaction);
            $request->save();

            return $request;
        });
    }

    /**
     * Staff transferred the money: the pending payout row succeeds with the bank's tracking number.
     *
     * @throws ValidationException
     */
    public function markWithdrawalPaid(WithdrawalRequest $request, User $staff, string $trackingCode, string $receiptPath): void
    {
        DB::transaction(function () use ($request, $staff, $trackingCode, $receiptPath): void {
            $request = WithdrawalRequest::lockForUpdate()->findOrFail($request->id);

            if ($request->status !== WithdrawalStatus::Pending) {
                throw ValidationException::withMessages(['tracking_code' => __('This request was already processed.')]);
            }

            $request->transaction?->update([
                'status' => TransactionStatus::Succeeded,
                'gateway' => 'bank_transfer',
                'gateway_ref' => $trackingCode,
            ]);

            $request->forceFill([
                'status' => WithdrawalStatus::Paid,
                'tracking_code' => $trackingCode,
                'receipt_path' => $receiptPath,
                'processed_by' => $staff->id,
                'processed_at' => now(),
            ])->save();
        });
    }

    /**
     * The request will not be paid (rejected by staff, or cancelled by the user): the money returns.
     *
     * @throws ValidationException
     */
    public function cancelWithdrawal(WithdrawalRequest $request, WithdrawalStatus $status, ?User $staff = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($request, $status, $staff, $reason): void {
            $request = WithdrawalRequest::lockForUpdate()->findOrFail($request->id);

            if ($request->status !== WithdrawalStatus::Pending) {
                throw ValidationException::withMessages(['status' => __('This request was already processed.')]);
            }

            $wallet = $this->lockWalletOf($request->user_id);
            $wallet->increment('balance', $request->amount);
            $request->transaction?->update(['status' => TransactionStatus::Cancelled]);

            $request->forceFill([
                'status' => $status,
                'rejection_reason' => $reason,
                'processed_by' => $staff?->id,
                'processed_at' => now(),
            ])->save();
        });
    }

    /**
     * Pay a skill exam from the freelancer's wallet when the attempt starts. The fee is not
     * returned when the attempt is failed or voided (leaving the exam page twice).
     *
     * @throws ValidationException
     */
    public function chargeExamFee(User $freelancer, AssessmentAttempt $attempt, int $amount): void
    {
        DB::transaction(function () use ($freelancer, $attempt, $amount): void {
            $wallet = $this->lockWalletOf($freelancer);

            if ($wallet->balance < $amount) {
                throw ValidationException::withMessages(['exam' => __('This exam costs :fee Toman. Top up :amount Toman first.', [
                    'fee' => PersianText::number($amount),
                    'amount' => PersianText::number($amount - $wallet->balance),
                ])]);
            }

            $wallet->decrement('balance', $amount);

            $wallet->transactions()->create([
                'assessment_attempt_id' => $attempt->id,
                'type' => TransactionType::ExamFee,
                'amount' => -$amount,
                'status' => TransactionStatus::Succeeded,
                'description' => __('Exam fee: :title', ['title' => $attempt->assessment->title]),
            ]);
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
