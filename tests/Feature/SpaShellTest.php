<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaShellTest extends TestCase
{
    public function test_spa_shell_is_served_for_application_routes(): void
    {
        $this->withoutVite();

        foreach (['/', '/login', '/dashboard'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('<div id="app"></div>', false);
        }
    }

    public function test_unknown_api_and_build_paths_return_json_not_found(): void
    {
        $this->withoutVite();

        foreach (['/api', '/api/', '/api/v1/missing', '/build', '/build/missing.js'] as $path) {
            $this->getJson($path)
                ->assertNotFound()
                ->assertExactJson([
                    'message' => 'Not found.',
                ]);
        }
    }
}
