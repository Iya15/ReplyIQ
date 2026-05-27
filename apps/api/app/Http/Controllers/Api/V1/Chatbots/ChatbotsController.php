<?php

namespace App\Http\Controllers\Api\V1\Chatbots;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chatbots\StoreChatbotRequest;
use App\Http\Requests\Chatbots\UpdateChatbotRequest;
use App\Http\Resources\ChatbotResource;
use App\Models\Chatbot;
use App\Repositories\ChatbotRepository;
use App\Services\Chatbot\CreateChatbotService;
use App\Services\Chatbot\DeleteChatbotService;
use App\Services\Chatbot\GenerateEmbedCodeService;
use App\Services\Chatbot\UpdateChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatbotsController extends Controller
{
    public function index(Request $r, ChatbotRepository $repo): JsonResponse
    {
        return $this->paginated($repo->paginate(), ChatbotResource::class, $r);
    }

    public function store(StoreChatbotRequest $r, CreateChatbotService $svc): JsonResponse
    {
        $chatbot = $svc->execute(app('currentOrganization'), $r->validated());

        return $this->ok(ChatbotResource::make($chatbot), $r, Response::HTTP_CREATED);
    }

    public function show(Request $r, Chatbot $chatbot): JsonResponse
    {
        $this->authorize('view', $chatbot);

        return $this->ok(ChatbotResource::make($chatbot->load('settings')), $r);
    }

    public function update(UpdateChatbotRequest $r, Chatbot $chatbot, UpdateChatbotService $svc): JsonResponse
    {
        $this->authorize('update', $chatbot);

        return $this->ok(ChatbotResource::make($svc->execute($chatbot, $r->validated())->load('settings')), $r);
    }

    public function destroy(Request $r, Chatbot $chatbot, DeleteChatbotService $svc): JsonResponse
    {
        $this->authorize('delete', $chatbot);
        $svc->execute($chatbot);

        return $this->ok(['message' => 'Chatbot deleted.'], $r);
    }

    public function embedCode(Request $r, Chatbot $chatbot, GenerateEmbedCodeService $svc): JsonResponse
    {
        $this->authorize('view', $chatbot);

        return $this->ok(['embed_code' => $svc->execute($chatbot)], $r);
    }
}
