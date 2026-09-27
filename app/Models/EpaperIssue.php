<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/** ई-पेपर का एक अंक (संस्करण + तारीख़) */
final class EpaperIssue extends Model
{
    protected static string $table = 'epaper_issues';
    protected static array $fillable = ['edition_id', 'issue_date', 'title', 'dir', 'pdf', 'pdf_size', 'cover', 'page_count', 'access', 'status', 'publish_at', 'created_by', 'updated_by'];

    public const ACCESS = ['free' => 'मुफ़्त', 'premium' => 'प्रीमियम'];
}
