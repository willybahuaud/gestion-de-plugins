<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewPurchaseNotification extends Notification
{
    use Queueable;

    public function __construct(
        public License $license
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $user = $this->license->user;
        $product = $this->license->product;
        $price = $this->license->price;

        $amount = $price ? number_format($price->amount / 100, 2, ',', ' ') . ' ' . strtoupper($price->currency) : 'N/A';
        $type = $this->license->type === 'subscription' ? 'Abonnement' : 'Licence lifetime';

        return (new MailMessage)
            ->subject('Nouvel achat - ' . ($product?->name ?? 'Produit'))
            ->greeting('Nouvel achat !')
            ->line("Un client vient d'effectuer un achat.")
            ->line('**Client :** ' . ($user?->email ?? 'N/A'))
            ->line('**Produit :** ' . ($product?->name ?? 'N/A'))
            ->line('**Type :** ' . $type)
            ->line('**Montant :** ' . $amount)
            ->line('**Licence :** ' . $this->license->uuid)
            ->action('Voir la licence', route('admin.licenses.show', $this->license))
            ->salutation('Plugin Hub');
    }
}
