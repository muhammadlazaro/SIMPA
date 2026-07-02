<?php

namespace Tests\Feature;

use App\Models\Aplikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AplikasiDocumentTest extends TestCase
{
    use RefreshDatabase;

    private const AUTH_HEADER_PREFIX = 'Bearer ';
    private const PDF_MIME_TYPE = 'application/pdf';

    public function test_pengelola_can_list_and_upload_document(): void
    {
        Storage::fake('public');

        $pengelola = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $token = $pengelola->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_ANALISA_DESAIN]);

        $list = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$app->id}/documents");
        $list->assertStatus(200)
            ->assertJsonPath('data.documents', []);

        $upload = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->post("/api/aplikasi/{$app->id}/documents", [
                'document_type' => 'laporan_analisa_desain',
                'file' => UploadedFile::fake()->create('laporan.pdf', 200, self::PDF_MIME_TYPE),
            ]);
        $upload->assertStatus(201)
            ->assertJsonPath('data.document.document_type', 'laporan_analisa_desain');

        $list2 = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$app->id}/documents");
        $list2->assertStatus(200);
        $this->assertCount(1, $list2->json('data.documents'));
    }

    public function test_unit_kerja_can_upload_allowed_type_on_own_app(): void
    {
        Storage::fake('public');

        $u = User::factory()->create(['role' => 'unit_kerja']);
        $token = $u->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create([
            'created_by' => $u->id,
            'status' => Aplikasi::STATUS_UAT,
        ]);

        $upload = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->post("/api/aplikasi/{$app->id}/documents", [
                'document_type' => 'uat',
                'file' => UploadedFile::fake()->create('uat.pdf', 100, self::PDF_MIME_TYPE),
            ]);
        $upload->assertStatus(201);
    }

    public function test_unit_kerja_cannot_upload_laporan_analisa_desain(): void
    {
        Storage::fake('public');

        $u = User::factory()->create(['role' => 'unit_kerja']);
        $token = $u->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create(['created_by' => $u->id]);

        $upload = $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->post("/api/aplikasi/{$app->id}/documents", [
                'document_type' => 'laporan_analisa_desain',
                'file' => UploadedFile::fake()->create('x.pdf', 100, self::PDF_MIME_TYPE),
            ]);
        $upload->assertStatus(403);
    }

    public function test_unit_kerja_cannot_access_other_unit_documents(): void
    {
        $u = User::factory()->create(['role' => 'unit_kerja']);
        $token = $u->createToken('t')->plainTextToken;
        $other = User::factory()->create(['role' => 'pengelola_aplikasi']);
        $app = Aplikasi::factory()->create(['created_by' => $other->id]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$app->id}/documents")
            ->assertStatus(403);
    }

    public function test_analis_can_upload_laporan_but_not_uat(): void
    {
        Storage::fake('public');

        $analis = User::factory()->create(['role' => 'analis_desain']);
        $token = $analis->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create(['status' => Aplikasi::STATUS_ANALISA_DESAIN]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->post("/api/aplikasi/{$app->id}/documents", [
                'document_type' => 'laporan_analisa_desain',
                'file' => UploadedFile::fake()->create('laporan.pdf', 120, self::PDF_MIME_TYPE),
            ])->assertStatus(201);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->post("/api/aplikasi/{$app->id}/documents", [
                'document_type' => 'uat',
                'file' => UploadedFile::fake()->create('uat.pdf', 120, self::PDF_MIME_TYPE),
            ])->assertStatus(403);
    }

    public function test_document_upload_is_blocked_when_status_is_not_in_document_stage(): void
    {
        Storage::fake('public');

        $u = User::factory()->create(['role' => 'unit_kerja']);
        $token = $u->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create([
            'created_by' => $u->id,
            'status' => Aplikasi::STATUS_DIAJUKAN,
        ]);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->post("/api/aplikasi/{$app->id}/documents", [
                'document_type' => 'uat',
                'file' => UploadedFile::fake()->create('uat.pdf', 120, self::PDF_MIME_TYPE),
            ])->assertStatus(422);
    }

    public function test_tim_uji_keamanan_can_view_but_cannot_upload_document(): void
    {
        Storage::fake('public');

        $timUji = User::factory()->create(['role' => 'tim_uji_keamanan']);
        $token = $timUji->createToken('t')->plainTextToken;
        $app = Aplikasi::factory()->create();

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->getJson("/api/aplikasi/{$app->id}/documents")
            ->assertStatus(200);

        $this->withHeader('Authorization', self::AUTH_HEADER_PREFIX.$token)
            ->post("/api/aplikasi/{$app->id}/documents", [
                'document_type' => 'uat',
                'file' => UploadedFile::fake()->create('uat.pdf', 120, self::PDF_MIME_TYPE),
            ])->assertStatus(403);
    }
}
