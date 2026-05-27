<?php

namespace App\Services\Knowledge;

use App\Enums\DocumentSourceType;
use App\Enums\DocumentStatus;
use App\Jobs\ProcessDocumentJob;
use App\Models\Chatbot;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class IngestUploadedFileService
{
    private const ALLOWED_MIMES = [
        'application/pdf' => DocumentSourceType::Pdf,
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => DocumentSourceType::Docx,
        'application/msword' => DocumentSourceType::Docx,
        'text/plain' => DocumentSourceType::Txt,
    ];

    private const MAX_BYTES = 25 * 1024 * 1024; // 25 MB (matches FormRequest max:25600)

    public function execute(Chatbot $chatbot, UploadedFile $file, ?string $title = null): Document
    {
        $this->validate($file);

        $mimeType = (string) $file->getMimeType();
        $sourceType = self::ALLOWED_MIMES[$mimeType];

        $ext = $file->getClientOriginalExtension() ?: $this->extFromMime($mimeType);
        $storageKey = sprintf(
            'documents/%s/%s/%s.%s',
            $chatbot->organization_id,
            $chatbot->id,
            Str::uuid(),
            $ext,
        );

        // Stream to S3 rather than loading the full file into memory.
        $handle = fopen($file->getPathname(), 'r');
        Storage::disk('s3')->put($storageKey, $handle);

        if (is_resource($handle)) {
            fclose($handle);
        }

        $document = Document::create([
            'organization_id' => $chatbot->organization_id,
            'chatbot_id' => $chatbot->id,
            'source_type' => $sourceType,
            'source_url' => $storageKey,
            'title' => $title ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'status' => DocumentStatus::Pending,
            'metadata' => [],
        ]);

        ProcessDocumentJob::dispatch($document);

        return $document;
    }

    private function validate(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('File exceeds the 20 MB size limit.');
        }

        $mimeType = (string) $file->getMimeType();

        if (! isset(self::ALLOWED_MIMES[$mimeType])) {
            throw new InvalidArgumentException(
                "Unsupported file type: {$mimeType}. Accepted: PDF, DOCX, TXT."
            );
        }
    }

    private function extFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/msword' => 'doc',
            'text/plain' => 'txt',
            default => 'bin',
        };
    }
}
