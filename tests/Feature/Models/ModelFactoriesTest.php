<?php

namespace Tests\Feature\Models;

use App\Models\Assessment;
use App\Models\AssessmentAttempt;
use App\Models\Badge;
use App\Models\Category;
use App\Models\CategoryBudgetRange;
use App\Models\Contract;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\EmployerProfile;
use App\Models\EmployerSubscription;
use App\Models\FreelancerField;
use App\Models\FreelancerProfile;
use App\Models\LearningContent;
use App\Models\MentorProfile;
use App\Models\MentorshipProgram;
use App\Models\MentorshipSession;
use App\Models\Message;
use App\Models\Milestone;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Review;
use App\Models\Skill;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Violation;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ModelFactoriesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<Model>}>
     */
    public static function models(): array
    {
        return collect([
            User::class, FreelancerProfile::class, EmployerProfile::class, MentorProfile::class,
            Plan::class, EmployerSubscription::class, Category::class, Skill::class,
            CategoryBudgetRange::class, FreelancerField::class, PortfolioItem::class, PortfolioMedia::class,
            Project::class, Proposal::class, Contract::class, Milestone::class, Wallet::class,
            Transaction::class, Review::class, Dispute::class, Ticket::class, MentorshipProgram::class,
            MentorshipSession::class, Assessment::class, AssessmentAttempt::class, LearningContent::class,
            Badge::class, Conversation::class, Message::class, Violation::class, PlatformSetting::class,
        ])->mapWithKeys(fn (string $model): array => [class_basename($model) => [$model]])->all();
    }

    /**
     * @param  class-string<Model>  $model
     */
    #[DataProvider('models')]
    public function test_factory_creates_a_record_that_satisfies_every_foreign_key(string $model): void
    {
        $record = $model::factory()->create();

        $this->assertModelExists($record);
    }

    public function test_contract_factory_links_the_proposal_project_and_both_parties(): void
    {
        $contract = Contract::factory()->create()->load('proposal.project');

        $this->assertSame($contract->proposal->project_id, $contract->project_id);
        $this->assertSame($contract->proposal->project->employer_id, $contract->employer_id);
        $this->assertSame($contract->proposal->freelancer_id, $contract->freelancer_id);
    }
}
