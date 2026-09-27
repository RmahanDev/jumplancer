<?php

namespace Tests\Feature\Models;

use App\Models\Message;
use App\Models\Violation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stores_the_short_alias_and_resolves_the_offending_message(): void
    {
        $message = Message::factory()->create();

        $violation = Violation::factory()->for($message, 'violatable')->create();

        $this->assertDatabaseHas('violations', ['id' => $violation->id, 'violatable_type' => 'message']);
        $this->assertTrue($violation->fresh()->violatable->is($message));
        $this->assertTrue($message->violations()->first()->is($violation));
    }
}
