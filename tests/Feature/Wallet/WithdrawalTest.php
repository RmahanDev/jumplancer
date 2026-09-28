<?php

namespace Tests\Feature\Wallet;

use App\Enums\AdminPermission;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\BankCard;
use App\Models\Proposal;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\WalletLedger;
use App\Support\BankCard as CardNumber;
use Database\Factories\BankCardFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class WithdrawalTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedReferenceData();
    }

    public function test_card_numbers_are_checked_with_luhn_and_named_after_their_bank(): void
    {
        $this->assertSame('6037991234567893', CardNumber::normalize('۶۰۳۷-۹۹۱۲ 3456 7893'));
        $this->assertTrue(CardNumber::isValid('6037991234567893'));
        $this->assertFalse(CardNumber::isValid('6037991234567890'));
        $this->assertFalse(CardNumber::isValid('0000000000000000'));
        $this->assertSame('بانک ملی ایران', CardNumber::bankName('6037991234567893'));
        $this->assertSame('6037-99**-****-7893', CardNumber::mask('6037991234567893'));
    }

    public function test_a_card_must_be_valid_in_the_users_name_and_on_their_mobile(): void
    {
        $user = $this->member();
        $this->actingAs($user);
        $valid = BankCardFactory::validNumber();

        $this->post(route('wallet.cards.store'), ['card_number' => '6037991234567890', 'holder_name' => $user->name, 'phone' => $user->phone, 'confirm_owner' => true])
            ->assertSessionHasErrors(['card_number' => 'شماره‌ی کارت معتبر نیست.']);
        $this->post(route('wallet.cards.store'), ['card_number' => $valid, 'holder_name' => 'شخص دیگر', 'phone' => $user->phone, 'confirm_owner' => true])
            ->assertSessionHasErrors(['holder_name' => "کارت باید به نام خودت ({$user->name}) باشد."]);
        $this->post(route('wallet.cards.store'), ['card_number' => $valid, 'holder_name' => $user->name, 'phone' => '09350000000', 'confirm_owner' => true])
            ->assertSessionHasErrors(['phone' => "کارت باید با شماره‌ی موبایل حسابت ({$user->phone}) ثبت شده باشد."]);
        $this->post(route('wallet.cards.store'), ['card_number' => $valid, 'holder_name' => $user->name, 'phone' => $user->phone])
            ->assertSessionHasErrors(['confirm_owner' => 'تأیید کن که این کارت به نام خودت است.']);

        $this->post(route('wallet.cards.store'), ['card_number' => implode('-', str_split($valid, 4)), 'holder_name' => $user->name, 'phone' => $user->phone, 'confirm_owner' => true])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast.message', 'کارتت ثبت شد. بازگشت وجه به همین کارت واریز می‌شود.');

        $card = BankCard::sole();
        $this->assertSame($valid, $card->card_number);
        $this->assertSame($user->name, $card->holder_name);
        $this->assertSame('بانک ملی ایران', $card->bank_name);

        $this->post(route('wallet.cards.store'), ['card_number' => $valid, 'holder_name' => $user->name, 'phone' => $user->phone, 'confirm_owner' => true])
            ->assertSessionHasErrors(['card_number' => 'این کارت قبلاً ثبت شده است.']);
    }

    public function test_users_without_a_mobile_are_asked_to_add_one_first(): void
    {
        $user = $this->member(phone: null);

        $this->actingAs($user)
            ->post(route('wallet.cards.store'), ['card_number' => BankCardFactory::validNumber(), 'holder_name' => $user->name, 'phone' => '09120000000', 'confirm_owner' => true])
            ->assertSessionHasErrors(['phone' => 'اول شماره‌ی موبایلت را در حساب کاربری ثبت کن؛ کارت باید با همین شماره ثبت شده باشد.']);
    }

    public function test_only_the_available_balance_can_be_withdrawn(): void
    {
        $employer = $this->employer(balance: 10_000_000);
        $employer->forceFill(['phone' => '09127654321'])->save();
        $card = BankCard::factory()->create(['user_id' => $employer->id]);

        // Hiring holds 45% of 10,000,000 in escrow, leaving 5,500,000 available.
        $proposal = Proposal::factory()->create(['project_id' => $this->openProject($employer)->id, 'freelancer_id' => $this->freelancer()->id, 'proposed_price' => 10_000_000]);
        $this->actingAs($employer)->post(route('employer.contracts.store', $proposal), ['accept_deposit_terms' => true])->assertSessionHasNoErrors();

        $this->post(route('wallet.withdrawals.store'), ['bank_card_id' => $card->id, 'amount' => 6_000_000])
            ->assertSessionHasErrors(['amount' => 'حداکثر ۵٬۵۰۰٬۰۰۰ تومان قابل برداشت است. پولی که در امانت است برداشتنی نیست.']);
        $this->post(route('wallet.withdrawals.store'), ['bank_card_id' => $card->id, 'amount' => 10_000])
            ->assertSessionHasErrors(['amount' => 'کمترین مبلغ برداشت ۵۰٬۰۰۰ تومان است.']);

        $this->post(route('wallet.withdrawals.store'), ['bank_card_id' => $card->id, 'amount' => 2_000_000])->assertSessionHasNoErrors();

        $wallet = $employer->wallet()->first();
        $this->assertSame([3_500_000, 4_500_000], [$wallet->balance, $wallet->held_balance]);

        $request = WithdrawalRequest::sole();
        $this->assertSame(WithdrawalStatus::Pending, $request->status);
        $this->assertSame($card->card_number, $request->card_number);
        $this->assertSame(TransactionType::Payout, $request->transaction->type);
        $this->assertSame(TransactionStatus::Pending, $request->transaction->status);
        $this->assertSame(-2_000_000, $request->transaction->amount);

        // "All of it" means the whole available balance, never the escrow.
        $this->post(route('wallet.withdrawals.store'), ['bank_card_id' => $card->id, 'withdraw_all' => true])->assertSessionHasNoErrors();
        $this->assertSame(3_500_000, WithdrawalRequest::latest('id')->first()->amount);
        $this->assertSame([0, 4_500_000], [$wallet->fresh()->balance, $wallet->fresh()->held_balance]);

        $this->post(route('wallet.withdrawals.store'), ['bank_card_id' => $card->id, 'withdraw_all' => true])
            ->assertSessionHasErrors(['amount' => 'در کیف پولت موجودی قابل برداشت نیست.']);
    }

    public function test_cards_of_other_users_cannot_be_used_or_removed(): void
    {
        $user = $this->member(balance: 1_000_000);
        $foreign = BankCard::factory()->create(['user_id' => $this->member()->id]);

        $this->actingAs($user)
            ->post(route('wallet.withdrawals.store'), ['bank_card_id' => $foreign->id, 'amount' => 500_000])
            ->assertSessionHasErrors('bank_card_id');
        $this->delete(route('wallet.cards.destroy', $foreign))->assertNotFound();
        $this->assertModelExists($foreign);
    }

    public function test_a_pending_request_can_be_cancelled_by_its_owner(): void
    {
        $user = $this->member(balance: 1_000_000);
        $card = BankCard::factory()->create(['user_id' => $user->id]);
        $request = app(WalletLedger::class)->requestWithdrawal($user, $card, 400_000);

        $this->actingAs($this->member())->delete(route('wallet.withdrawals.destroy', $request))->assertNotFound();

        $this->actingAs($user);
        $this->delete(route('wallet.cards.destroy', $card))->assertSessionHasErrors(['card' => 'یک درخواست واریز به این کارت هنوز در حال بررسی است.']);
        $this->delete(route('wallet.withdrawals.destroy', $request))->assertSessionHasNoErrors();

        $this->assertSame(WithdrawalStatus::Cancelled, $request->fresh()->status);
        $this->assertSame(TransactionStatus::Cancelled, $request->fresh()->transaction->status);
        $this->assertSame(1_000_000, $user->wallet()->first()->balance);
        $this->delete(route('wallet.withdrawals.destroy', $request))->assertSessionHasErrors('status');

        $this->get(route('wallet.show'))->assertInertia(fn (Assert $page) => $page
            ->has('cards', 1)
            ->where('cards.0.card_masked', CardNumber::mask($card->card_number))
            ->missing('cards.0.card_number')
            ->where('withdrawals.0.status', 'cancelled')
            ->where('withdrawals.0.card_number', CardNumber::mask($card->card_number)));
    }

    public function test_staff_pay_with_a_tracking_number_and_a_receipt_image(): void
    {
        Storage::fake('local');
        $user = $this->member(balance: 2_000_000);
        $card = BankCard::factory()->create(['user_id' => $user->id]);
        $request = app(WalletLedger::class)->requestWithdrawal($user, $card, 1_500_000);
        $support = $this->supportAgent();

        $this->actingAs($support);
        $this->get(route('admin.withdrawals.index'))->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'pending')
            ->where('pendingTotal', 1_500_000)
            ->where('withdrawals.data.0.card_number', $card->card_number)
            ->where('withdrawals.data.0.card_phone', $card->phone)
            ->where('withdrawals.data.0.user.roles', ['freelancer']));

        $this->post(route('admin.withdrawals.pay', $request), ['tracking_code' => '123456789'])
            ->assertSessionHasErrors(['receipt' => 'تصویر رسید پرداخت را پیوست کن.']);
        $this->post(route('admin.withdrawals.pay', $request), ['tracking_code' => '123456789', 'receipt' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors('receipt');

        $this->post(route('admin.withdrawals.pay', $request), ['tracking_code' => '۱۲۳۴۵۶۷۸۹', 'receipt' => UploadedFile::fake()->image('receipt.jpg')])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame(WithdrawalStatus::Paid, $request->status);
        $this->assertSame('123456789', $request->tracking_code);
        $this->assertSame($support->id, $request->processed_by);
        Storage::disk('local')->assertExists($request->receipt_path);
        $this->assertSame(TransactionStatus::Succeeded, $request->transaction->status);
        $this->assertSame('123456789', $request->transaction->gateway_ref);
        $this->assertSame(500_000, $user->wallet()->first()->balance);

        $this->post(route('admin.withdrawals.pay', $request), ['tracking_code' => '999999', 'receipt' => UploadedFile::fake()->image('again.jpg')])
            ->assertSessionHasErrors('tracking_code');

        // The receipt is private: the owner and payout staff only.
        $this->get(route('withdrawals.receipt', $request))->assertOk();
        $this->actingAs($user)->get(route('withdrawals.receipt', $request))->assertOk();
        $this->actingAs($this->member())->get(route('withdrawals.receipt', $request))->assertNotFound();
    }

    public function test_rejecting_returns_the_money_with_a_reason(): void
    {
        $user = $this->member(balance: 1_000_000);
        $request = app(WalletLedger::class)->requestWithdrawal($user, BankCard::factory()->create(['user_id' => $user->id]), 1_000_000);

        $this->actingAs($this->supportAgent());
        $this->put(route('admin.withdrawals.reject', $request), [])->assertSessionHasErrors('rejection_reason');
        $this->put(route('admin.withdrawals.reject', $request), ['rejection_reason' => 'نام صاحب کارت با حساب یکی نیست.'])->assertSessionHasNoErrors();

        $this->assertSame(WithdrawalStatus::Rejected, $request->fresh()->status);
        $this->assertSame('نام صاحب کارت با حساب یکی نیست.', $request->fresh()->rejection_reason);
        $this->assertSame(1_000_000, $user->wallet()->first()->balance);
    }

    public function test_payouts_need_the_withdrawals_permission(): void
    {
        $request = WithdrawalRequest::factory()->create();

        $this->actingAs($this->adminWith([AdminPermission::ViewFinance]));
        $this->get(route('admin.withdrawals.index'))->assertForbidden();
        $this->put(route('admin.withdrawals.reject', $request), ['rejection_reason' => 'بدون دسترسی'])->assertForbidden();
        $this->get(route('admin.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('panel.navigation', fn ($items) => collect($items)->doesntContain('key', 'admin.withdrawals.index')));
    }

    private function member(int $balance = 0, ?string $phone = '09121234567'): User
    {
        $user = $this->freelancer();
        $user->forceFill(['phone' => $phone === null ? null : '0912'.random_int(1_000_000, 9_999_999)])->save();

        if ($balance > 0) {
            app(WalletLedger::class)->deposit($user, $balance);
        }

        return $user->refresh();
    }

    private function supportAgent(): User
    {
        $user = User::factory()->create(['username' => 'support'.random_int(1000, 9999)]);
        $user->assignRole('support');
        $user->givePermissionTo(array_map(fn (AdminPermission $permission): string => $permission->value, AdminPermission::supportDefaults()));

        return $user->refresh();
    }
}
