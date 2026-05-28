<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlanLimitWarningNotification extends Notification
{
    /**
     * @param  int<80,100>  $percent  80 or 100
     */
    public function __construct(
        private readonly Organization $organization,
        private readonly string       $metric,
        private readonly int          $current,
        private readonly int          $limit,
        private readonly int          $percent,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $billingUrl  = rtrim((string) config('app.frontend_url', ''), '/') . '/billing';
        $metricLabel = str_replace('_', ' ', $this->metric);
        $reached     = $this->percent >= 100;

        $subject = $reached
            ? "[ReplyIQ] You've reached your {$metricLabel} limit"
            : "[ReplyIQ] You're at {$this->percent}% of your {$metricLabel} limit";

        $message = (new MailMessage)->subject($subject);

        if ($reached) {
            $message
                ->greeting("You've hit your limit")
                ->line("Your organization **{$this->organization->name}** has reached the {$metricLabel} limit on the **{$this->organization->plan}** plan ({$this->current}/{$this->limit}).")
                ->line('New items cannot be created until you upgrade or the counter resets next month.')
                ->action('Upgrade your plan', $billingUrl);
        } else {
            $message
                ->greeting("Heads up — {$this->percent}% of your limit used")
                ->line("Your organization **{$this->organization->name}** has used {$this->current} of {$this->limit} {$metricLabel} on the **{$this->organization->plan}** plan.")
                ->line('Upgrade now to avoid interruptions.')
                ->action('View billing', $billingUrl);
        }

        return $message->line("You can view and manage your usage at any time in the billing dashboard.");
    }
}
