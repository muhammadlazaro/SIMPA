<?php

namespace Tests\Unit;

use App\Http\Requests\StoreAplikasiDocumentRequest;
use App\Http\Requests\StoreRfcRequest;
use App\Http\Requests\UpdateRfcRequest;
use PHPUnit\Framework\TestCase;

class UploadRequestSecurityTest extends TestCase
{
    public function test_document_and_rfc_uploads_are_limited_to_eight_megabytes(): void
    {
        $rules = [
            (new StoreAplikasiDocumentRequest)->rules()['file'],
            (new StoreRfcRequest)->rules()['formulir_rfc'],
            (new UpdateRfcRequest)->rules()['formulir_rfc'],
        ];

        foreach ($rules as $fileRules) {
            $this->assertContains('max:8000', $fileRules);
            $this->assertNotContains('max:10240', $fileRules);
        }
    }
}
