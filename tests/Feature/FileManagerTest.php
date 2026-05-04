<?php

namespace Tests\Feature;

use Aero\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_manager_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('core.file-manager.index'));
        $this->assertTrue(Route::has('core.file-manager.browse'));
        $this->assertTrue(Route::has('core.file-manager.upload'));
        $this->assertTrue(Route::has('core.file-manager.destroy'));
        $this->assertTrue(Route::has('core.file-manager.stats'));
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get(route('core.file-manager.index'));
        $response->assertRedirect();
    }

    public function test_index_returns_inertia_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('core.file-manager.index'));

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Core/FileManager/Index')
                ->has('title')
            );
    }

    public function test_browse_json_returns_structure(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('core.file-manager.browse'));

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_path',
                'parent_path',
            ]);
    }

    public function test_upload_validates_file(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(route('core.file-manager.upload'), [
                'file' => 'not-a-file',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    }

    public function test_routes_have_hrmac_middleware(): void
    {
        $index = Route::getRoutes()->getByName('core.file-manager.index');
        $this->assertNotNull($index);
        $this->assertContains('hrmac:core.file_manager.storage.view', $index->gatherMiddleware());

        $upload = Route::getRoutes()->getByName('core.file-manager.upload');
        $this->assertNotNull($upload);
        $this->assertContains('hrmac:core.file_manager.storage.edit', $upload->gatherMiddleware());

        $destroy = Route::getRoutes()->getByName('core.file-manager.destroy');
        $this->assertNotNull($destroy);
        $this->assertContains('hrmac:core.file_manager.storage.delete', $destroy->gatherMiddleware());
    }
}
