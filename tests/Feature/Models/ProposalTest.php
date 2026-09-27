<?php

namespace Tests\Feature\Models;

use App\Models\Project;
use App\Models\Proposal;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProposalTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_a_second_proposal_from_the_same_freelancer_on_the_same_project(): void
    {
        $project = Project::factory()->create();
        $freelancer = User::factory()->freelancer()->create();
        Proposal::factory()->for($project)->for($freelancer, 'freelancer')->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Proposal::factory()->for($project)->for($freelancer, 'freelancer')->create();
    }

    public function test_deleting_a_project_deletes_its_proposals(): void
    {
        $proposal = Proposal::factory()->create();

        $proposal->project->forceDelete();

        $this->assertModelMissing($proposal);
    }
}
