<?php

namespace Tests\Feature\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_states_assign_the_role_and_create_the_matching_profile(): void
    {
        $freelancer = User::factory()->freelancer()->create();
        $employer = User::factory()->employer()->create();
        $mentor = User::factory()->mentor()->create();

        $this->assertTrue($freelancer->hasRole(RoleName::Freelancer));
        $this->assertNotNull($freelancer->freelancerProfile);
        $this->assertTrue($employer->hasRole(RoleName::Employer));
        $this->assertNotNull($employer->employerProfile);
        $this->assertTrue($mentor->hasRole(RoleName::Mentor));
        $this->assertNotNull($mentor->mentorProfile);
    }

    public function test_a_user_can_hold_several_roles(): void
    {
        $user = User::factory()->freelancer()->employer()->create();

        $this->assertTrue($user->hasAllRoles([RoleName::Freelancer, RoleName::Employer]));
        $this->assertNotNull($user->freelancerProfile);
        $this->assertNotNull($user->employerProfile);
    }

    /**
     * @return array<string, array{UserStatus, bool}>
     */
    public static function statuses(): array
    {
        return [
            'active' => [UserStatus::Active, false],
            'suspended' => [UserStatus::Suspended, true],
            'banned' => [UserStatus::Banned, true],
        ];
    }

    #[DataProvider('statuses')]
    public function test_is_suspended_reflects_the_account_status(UserStatus $status, bool $expected): void
    {
        $user = User::factory()->create(['status' => $status]);

        $this->assertSame($expected, $user->isSuspended());
    }

    public function test_role_assignment_stores_the_short_morph_alias(): void
    {
        User::factory()->admin()->create();

        $this->assertDatabaseHas('model_has_roles', ['model_type' => 'user']);
    }

    public function test_force_deleting_a_user_with_ledger_rows_is_rejected(): void
    {
        $wallet = Wallet::factory()->create();
        Transaction::factory()->for($wallet)->create();

        $this->expectException(QueryException::class);

        $wallet->user->forceDelete();
    }
}
