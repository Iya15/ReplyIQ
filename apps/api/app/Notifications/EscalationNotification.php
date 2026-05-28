<?php

namespace App\Notifications;

use App\Models\Chatbot;
use App\Models\Conversation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EscalationNotification extends Notification
{
    public function __construct(
        private readonly Conversation $conversation,
        private readonly Chatbot      $chatbot,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dashboardUrl = rtrim((string) config('app.frontend_url', ''), '/')
            . '/chatbots/' . $this->chatbot->id . '/conversations';

        return (new MailMessage)
            ->subject("[{$this->chatbot->name}] A visitor is requesting human support")
            ->greeting('Human support requested')
            ->line("A visitor on **{$this->chatbot->name}** has requested to speak with a human agent.")
            ->line("Visitor ID: `{$this->conversation->visitor_id}`")
            ->when($this->conversation->source_url, fn ($m) =>
                $m->line("Page: {$this->conversation->source_url}")
            )
            ->action('View conversation', $dashboardUrl)
            ->line('Open the conversation in the dashboard to take over and respond.');
    }

    /**
     * Optionally POST a Slack notification if SLACK_ESCALATION_WEBHOOK_URL is set.
     */
    public static function notifySlack(Conversation $conversation, Chatbot $chatbot): void
    {
        $webhookUrl = config('services.slack.escalation_webhook_url');
        if (! $webhookUrl) {
            return;
        }

        try {
            Http::post((string) $webhookUrl, [
                'text' => ":raising_hand: *Human support requested* on *{$chatbot->name}*\n"
                    . "Visitor: `{$conversation->visitor_id}`"
                    . ($conversation->source_url ? "\nPage: {$conversation->source_url}" : ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('EscalationNotification: Slack webhook failed', ['error' => $e->getMessage()]);
        }
    }
}
