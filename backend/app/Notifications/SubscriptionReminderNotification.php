<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Subscription $subscription, public string $event) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan->name;
        $endsAt = $this->subscription->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i');
        $message = (new MailMessage)->greeting('Xin chào '.$notifiable->name.',');

        if ($this->event === 'expired') {
            return $message
                ->subject('Gói Vive Host đã hết hạn')
                ->line("Gói {$plan} đã hết hạn lúc {$endsAt}.")
                ->line('Ứng dụng hiện tại không bị xóa. Bạn còn 3 ngày gia hạn trước khi các thao tác cấp tài nguyên mới bị khóa hoàn toàn.')
                ->line('Your existing applications are not deleted. Renew during the grace period to keep provisioning and deployment access.');
        }

        return $message
            ->subject("Gói Vive Host còn {$this->event} ngày")
            ->line("Gói {$plan} sẽ hết hạn lúc {$endsAt} (còn {$this->event} ngày).")
            ->line('Vui lòng liên hệ quản trị viên để gia hạn. Hệ thống sẽ không tự động trừ tiền.')
            ->line("Your Vive Host plan expires in {$this->event} day(s). No automatic charge will be made.");
    }
}
