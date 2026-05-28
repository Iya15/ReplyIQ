<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function __construct(private readonly User $user) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dashboardUrl = rtrim((string) config('app.frontend_url', ''), '/') . '/chatbots';

        return (new MailMessage)
            ->subject("Welcome to ReplyIQ, {$this->user->name}!")
            ->greeting("Welcome aboard, {$this->user->name}! 👋")
            ->line("We're excited to have you. ReplyIQ lets you build an AI chatbot trained on your content in minutes.")
            ->line("We've already created a sample chatbot for you — log in to see it in action.")
            ->action('Go to your dashboard', $dashboardUrl)
            ->line('**What to do next:**')
            ->line('1. Upload your documents or paste your FAQ content.')
            ->line('2. Customize the widget to match your brand.')
            ->line('3. Copy the embed snippet to your website.')
            ->line("Need help? Reply to this email or visit the docs at app.replyiq.com/docs.");
    }
}
