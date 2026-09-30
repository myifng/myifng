<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class NewsletterTemplate extends Model
{
    protected static string $table = 'newsletter_templates';
    protected static array $fillable = ['name', 'html', 'is_default'];
}
