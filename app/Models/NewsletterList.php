<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class NewsletterList extends Model
{
    protected static string $table = 'newsletter_lists';
    protected static array $fillable = ['name', 'description', 'category_id', 'is_default', 'is_public'];
}
