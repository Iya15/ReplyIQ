<?php

namespace App\Policies;

use App\Models\Chatbot;
use App\Models\User;

class ChatbotPolicy
{
    public function view(User $user, Chatbot $chatbot): bool
    {
        // TenantScope + route model binding already returns 404 for cross-tenant
        // access; this check is defense-in-depth.
        return $user->organizations()
            ->where('organizations.id', $chatbot->organization_id)
            ->exists();
    }

    public function update(User $user, Chatbot $chatbot): bool
    {
        return $this->view($user, $chatbot);
    }

    public function delete(User $user, Chatbot $chatbot): bool
    {
        // Deletion requires admin or owner role — members cannot delete chatbots.
        return $user->organizations()
            ->where('organizations.id', $chatbot->organization_id)
            ->wherePivotIn('role', ['owner', 'admin'])
            ->exists();
    }
}
