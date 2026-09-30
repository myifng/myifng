<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class NewsletterSubscriber extends Model
{
    protected static string $table = 'newsletter_subscribers';
    protected static array $fillable = ['email', 'name', 'status', 'token', 'source', 'reader_id', 'confirmed_at', 'unsubscribed_at'];

    public const STATUSES = ['pending' => 'पुष्टि बाकी', 'subscribed' => 'सब्सक्राइब्ड', 'unsubscribed' => 'अनसब्सक्राइब', 'bounced' => 'बाउंस'];
    public const BADGE = ['pending' => 'warning', 'subscribed' => 'success', 'unsubscribed' => 'secondary', 'bounced' => 'dark'];
}
