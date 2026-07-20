<?php

namespace Tests\Feature;

use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AplikasiDiscussionTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        $this->app['auth']->forgetGuards();

        return 'Bearer '.$user->createToken('discussion-test')->plainTextToken;
    }

    public function test_participants_can_reply_and_author_can_edit_threaded_notes(): void
    {
        $unit = User::factory()->create(['role' => 'unit_kerja']);
        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $app = Aplikasi::factory()->create(['created_by' => $unit->id]);

        $root = $this->withHeader('Authorization', $this->token($unit))
            ->postJson("/api/aplikasi/{$app->id}/notes", [
                'note_type' => 'info',
                'body' => 'Mohon tinjau perubahan ruang lingkup.',
            ])
            ->assertCreated();
        $rootId = $root->json('data.note.id');

        $reply = $this->withHeader('Authorization', $this->token($pengelola))
            ->postJson("/api/aplikasi/{$app->id}/notes", [
                'note_type' => 'info',
                'body' => 'Sudah diterima dan sedang ditinjau.',
                'parent_id' => $rootId,
            ])
            ->assertCreated();
        $replyId = $reply->json('data.note.id');

        $this->withHeader('Authorization', $this->token($pengelola))
            ->patchJson("/api/aplikasi/{$app->id}/notes/{$replyId}", [
                'body' => 'Sudah diterima dan masuk antrian verifikasi.',
            ])
            ->assertOk()
            ->assertJsonPath('data.note.body', 'Sudah diterima dan masuk antrian verifikasi.')
            ->assertJsonPath('data.note.edited_at', fn ($value) => is_string($value));

        $this->withHeader('Authorization', $this->token($unit))
            ->getJson("/api/aplikasi/{$app->id}/workflow")
            ->assertOk()
            ->assertJsonPath('data.notes.0.id', $rootId)
            ->assertJsonPath('data.notes.0.replies.0.id', $replyId)
            ->assertJsonPath('data.notes.0.replies.0.creator.role', 'pengelola_aplikasi');

        $this->withHeader('Authorization', $this->token($unit))
            ->patchJson("/api/aplikasi/{$app->id}/notes/{$replyId}", ['body' => 'Diubah pihak lain'])
            ->assertForbidden();
    }

    public function test_deleting_a_parent_note_removes_its_replies(): void
    {
        $unit = User::factory()->create(['role' => 'unit_kerja']);
        $app = Aplikasi::factory()->create(['created_by' => $unit->id]);
        $authorization = $this->token($unit);

        $rootId = $this->withHeader('Authorization', $authorization)
            ->postJson("/api/aplikasi/{$app->id}/notes", ['body' => 'Catatan utama'])
            ->json('data.note.id');
        $replyId = $this->withHeader('Authorization', $authorization)
            ->postJson("/api/aplikasi/{$app->id}/notes", [
                'body' => 'Balasan',
                'parent_id' => $rootId,
            ])
            ->json('data.note.id');

        $this->withHeader('Authorization', $authorization)
            ->deleteJson("/api/aplikasi/{$app->id}/notes/{$rootId}")
            ->assertOk();

        $this->assertDatabaseMissing('aplikasi_notes', ['id' => $rootId]);
        $this->assertDatabaseMissing('aplikasi_notes', ['id' => $replyId]);
    }
}
