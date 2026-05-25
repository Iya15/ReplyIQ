<?php

namespace Database\Seeders;

use App\Models\Chatbot;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::create([
            'name' => 'ReplyIQ Demo',
            'slug' => 'replyiq-demo',
            'plan' => 'free',
            'settings' => [],
        ]);

        $user = User::create([
            'name' => 'Demo User',
            'email' => 'demo@replyiq.test',
            'password_hash' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        Membership::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        // Demo chatbots — settings are auto-created by ChatbotObserver.
        $support = Chatbot::create([
            'organization_id' => $org->id,
            'name' => 'Support Bot',
            'status' => 'active',
            'language' => 'en',
        ]);

        $support->settings->update([
            'welcome_message' => 'Hi! I\'m the ReplyIQ support bot. How can I help you today?',
        ]);

        Chatbot::create([
            'organization_id' => $org->id,
            'name' => 'Sales Bot',
            'status' => 'draft',
            'language' => 'en',
        ]);
    }
}
