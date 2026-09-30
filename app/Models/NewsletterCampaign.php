<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class NewsletterCampaign extends Model
{
    protected static string $table = 'newsletter_campaigns';
    protected static array $fillable = ['subject', 'preheader', 'content', 'include_top', 'template_id', 'list_ids', 'status', 'scheduled_at', 'started_at', 'finished_at', 'recipients', 'sent', 'failed', 'created_by'];

    public const STATUSES = ['draft' => 'ड्राफ़्ट', 'scheduled' => 'तय समय पर', 'sending' => 'भेजा जा रहा', 'sent' => 'भेज दिया', 'cancelled' => 'रद्द'];
    public const BADGE = ['draft' => 'secondary', 'scheduled' => 'info', 'sending' => 'warning', 'sent' => 'success', 'cancelled' => 'dark'];
}
