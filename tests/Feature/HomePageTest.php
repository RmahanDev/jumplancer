<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_homepage_shows_marketplace_sections(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertViewIs('index')
            ->assertSee('Browse by category')
            ->assertSee('Programming & Tech', escape: true)
            ->assertSee('Latest projects')
            ->assertSee('Top freelancers');
    }
}
