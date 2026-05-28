<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\StoreDocumentFileRequest;
use App\Http\Requests\Documents\StoreDocumentTextRequest;
use App\Http\Requests\Documents\StoreDocumentUrlRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Chatbot;
use App\Models\Document;
use App\Repositories\DocumentRepository;
use App\Services\Knowledge\IngestCrawledUrlService;
use App\Services\Knowledge\IngestManualTextService;
use App\Services\Knowledge\IngestUploadedFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DocumentsController extends Controller
{
    public function index(Request $r, Chatbot $chatbot, DocumentRepository $repo): JsonResponse
    {
        $this->authorize('viewAny', [Document::class, $chatbot]);

        return $this->paginated(
            $repo->paginate($chatbot, $r->only(['status', 'source_type'])),
            DocumentResource::class,
            $r,
        );
    }

    public function storeFile(
        StoreDocumentFileRequest $r,
        Chatbot $chatbot,
        IngestUploadedFileService $svc,
    ): JsonResponse {
        $this->authorize('create', [Document::class, $chatbot]);

        // TODO: Check organization plan limits (max documents per chatbot) — Phase 4

        $document = $svc->execute($chatbot, $r->file('file'), $r->input('title'));

        return $this->ok(DocumentResource::make($document), $r, Response::HTTP_ACCEPTED);
    }

    public function storeText(
        StoreDocumentTextRequest $r,
        Chatbot $chatbot,
        IngestManualTextService $svc,
    ): JsonResponse {
        $this->authorize('create', [Document::class, $chatbot]);

        // TODO: Check organization plan limits (max documents per chatbot) — Phase 4

        $document = $svc->execute(
            chatbot: $chatbot,
            title: $r->string('title')->toString(),
            content: $r->string('content')->toString(),
        );

        return $this->ok(DocumentResource::make($document), $r, Response::HTTP_ACCEPTED);
    }

    public function storeUrl(
        StoreDocumentUrlRequest $r,
        Chatbot $chatbot,
        IngestCrawledUrlService $svc,
    ): JsonResponse {
        $this->authorize('create', [Document::class, $chatbot]);

        $document = $svc->execute(
            chatbot: $chatbot,
            url: $r->string('url')->toString(),
            maxPages: (int) $r->input('max_pages', 50),
        );

        return $this->ok(DocumentResource::make($document), $r, Response::HTTP_ACCEPTED);
    }

    public function destroy(Request $r, Document $document, DocumentRepository $repo): JsonResponse
    {
        $this->authorize('delete', $document);

        $repo->delete($document);

        return $this->ok(['message' => 'Document deleted.'], $r);
    }

    public function reprocess(Request $r, Document $document, DocumentRepository $repo): JsonResponse
    {
        $this->authorize('update', $document);

        $repo->reprocess($document);

        return $this->ok(DocumentResource::make($document->fresh()), $r, Response::HTTP_ACCEPTED);
    }
}
