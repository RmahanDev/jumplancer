<?php

namespace Tests\Feature\Models;

use App\Enums\ModerationStatus;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_media_hides_files_that_are_pending_or_rejected(): void
    {
        $item = PortfolioItem::factory()->create();
        $approved = PortfolioMedia::factory()->for($item)->approved()->create();
        PortfolioMedia::factory()->for($item)->create();
        PortfolioMedia::factory()->for($item)->create(['status' => ModerationStatus::Rejected]);

        $this->assertSame([$approved->id], $item->approvedMedia()->pluck('id')->all());
        $this->assertSame(3, $item->media()->count());
    }

    public function test_deleting_a_reviewer_keeps_the_media_and_clears_the_reviewer(): void
    {
        $media = PortfolioMedia::factory()->approved()->create();
        $reviewer = User::factory()->admin()->create();
        $media->reviewer()->associate($reviewer)->save();

        $reviewer->forceDelete();

        $this->assertNull($media->fresh()->reviewed_by);
    }
}
