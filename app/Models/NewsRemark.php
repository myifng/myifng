<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** टिप्पणी: note = आंतरिक (सिर्फ़ डेस्क), feedback = रिपोर्टर को दिखे, status = स्थिति बदलने का रिकॉर्ड */
final class NewsRemark extends Model
{
    protected static string $table = 'news_remarks';
    protected static array $fillable = ['news_id', 'user_id', 'type', 'message', 'from_status', 'to_status', 'created_at'];
    protected static bool $timestamps = false;
}
