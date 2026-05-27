<?php

namespace App\Policies;

use App\Models\Chatbot;
use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function viewAny(User $user, Chatbot $chatbot): bool
    {
        return $user->organizations()
            ->where('organizations.id', $chatbot->organization_id)
            ->exists();
    }

    public function create(User $user, Chatbot $chatbot): bool
    {
        return $this->viewAny($user, $chatbot);
    }

    public function update(User $user, Document $document): bool
    {
        return $user->organizations()
            ->where('organizations.id', $document->organization_id)
            ->exists();
    }

    public function delete(User $user, Document $document): bool
    {
        return $this->update($user, $document);
    }
}
