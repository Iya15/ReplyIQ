<?php

namespace App\Notifications;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvitationNotification extends Notification
{
    public function __construct(
        private readonly Invitation   $invitation,
        private readonly Organization $organization,
        private readonly User         $inviter,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $acceptUrl = rtrim((string) config('app.frontend_url', ''), '/')
            . '/invitations/' . $this->invitation->token . '/accept';

        return (new MailMessage)
            ->subject("{$this->inviter->name} invited you to {$this->organization->name} on ReplyIQ")
            ->greeting("You've been invited!")
            ->line("{$this->inviter->name} has invited you to join **{$this->organization->name}** on ReplyIQ as a **{$this->invitation->role}**.")
            ->action('Accept Invitation', $acceptUrl)
            ->line("This invitation expires in 7 days.")
            ->line("If you didn't expect this invitation, you can safely ignore this email.");
    }
}
