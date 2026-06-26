<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserNote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserNoteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_manage_shared_panel_notes_and_tasks(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'menu_permissions' => ['notes'],
        ]);

        $this->actingAs($user);

        $createResponse = $this->postJson('/api/notes', [
            'title' => 'Bugünkü sevkiyatları kontrol et',
            'body' => 'Erzurum ve Trabzon hazırlıklarını sırayla tamamla.',
            'priority' => 'high',
            'is_pinned' => true,
            'due_date' => '2026-06-05',
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.created_by.username', $user->username)
            ->assertJsonPath('data.title', 'Bugünkü sevkiyatları kontrol et')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.is_pinned', true)
            ->assertJsonPath('data.due_date', '2026-06-05');

        $noteId = (int) $createResponse->json('data.id');

        $this->getJson('/api/notes')
            ->assertOk()
            ->assertJsonPath('summary.open', 1)
            ->assertJsonPath('summary.done', 0)
            ->assertJsonPath('data.0.id', $noteId);

        $this->patchJson("/api/notes/{$noteId}", [
            'status' => 'done',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->assertNotNull(UserNote::query()->findOrFail($noteId)->completed_at);

        $this->getJson('/api/notes?status=done')
            ->assertOk()
            ->assertJsonPath('summary.open', 0)
            ->assertJsonPath('summary.done', 1)
            ->assertJsonPath('data.0.id', $noteId);

        $this->deleteJson("/api/notes/{$noteId}")
            ->assertOk()
            ->assertJsonPath('message', 'Not silindi.');

        $this->assertDatabaseMissing('user_notes', ['id' => $noteId]);
    }

    public function test_panel_notes_are_shared_between_users(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'menu_permissions' => ['notes'],
        ]);
        $otherUser = User::factory()->create(['is_active' => true]);
        $otherNote = UserNote::query()->create([
            'user_id' => $otherUser->id,
            'title' => 'Başkasının notu',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($user);

        $this->getJson('/api/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $otherNote->id);

        $this->patchJson("/api/notes/{$otherNote->id}", [
            'status' => 'done',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'done');

        $this->deleteJson("/api/notes/{$otherNote->id}")
            ->assertOk();
    }

    public function test_erzurum_quick_sale_notes_are_private_from_admin_notes(): void
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'menu_permissions' => ['notes'],
        ]);
        $quickSaleUser = User::factory()->create([
            'username' => 'erzurum.hizlisatis',
            'is_active' => true,
            'menu_permissions' => ['notes'],
        ]);

        $adminNote = UserNote::query()->create([
            'user_id' => $admin->id,
            'title' => 'Admin notu',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $quickSaleNote = UserNote::query()->create([
            'user_id' => $quickSaleUser->id,
            'title' => 'Erzurum hizli satis notu',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->actingAs($quickSaleUser);

        $this->getJson('/api/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $quickSaleNote->id)
            ->assertJsonPath('summary.open', 1);

        $this->patchJson("/api/notes/{$adminNote->id}", [
            'status' => 'done',
        ])->assertNotFound();

        $this->actingAs($admin);

        $this->getJson('/api/notes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $adminNote->id)
            ->assertJsonPath('summary.open', 1);

        $this->deleteJson("/api/notes/{$quickSaleNote->id}")
            ->assertNotFound();
    }

    public function test_user_without_notes_permission_cannot_access_panel_notes(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'menu_permissions' => ['dashboard'],
        ]);

        $this->actingAs($user);

        $this->getJson('/api/notes')
            ->assertForbidden();
    }
}
