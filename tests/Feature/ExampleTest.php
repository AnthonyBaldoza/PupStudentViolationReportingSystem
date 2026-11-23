<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Replaces Laravel's default example test (which expects "/" to return 200).
 * SVMS has no public home page: "/" goes to the dashboard, which requires a login.
 */
class ExampleTest extends TestCase
{
    public function test_the_application_requires_authentication(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertRedirect('/login');
    }
}
