<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SellerProductSubmitted extends Notification
{
    use Queueable;

    public function __construct(private readonly Product $product)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'event' => 'seller_product_submitted',
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'seller_id' => $this->product->seller_id,
            'message' => 'A seller submitted a product for review.',
        ];
    }
}
