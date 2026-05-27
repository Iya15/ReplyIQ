<?php

namespace App\Repositories;

use App\Models\Chatbot;
use Illuminate\Pagination\LengthAwarePaginator;

class ChatbotRepository
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        // TenantScope on Chatbot automatically filters to the current org.
        return Chatbot::with('settings')->latest()->paginate($perPage);
    }

    public function find(string $id): Chatbot
    {
        return Chatbot::with('settings')->findOrFail($id);
    }

    public function create(array $data): Chatbot
    {
        return Chatbot::create($data);
    }

    public function update(Chatbot $chatbot, array $data): Chatbot
    {
        $chatbot->update($data);

        return $chatbot;
    }

    public function delete(Chatbot $chatbot): void
    {
        $chatbot->delete();
    }
}
