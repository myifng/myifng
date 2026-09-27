<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Ad extends Model
{
    protected static string $table = 'ads';
    protected static array $fillable = ['campaign_id', 'name', 'type', 'image', 'mobile_image', 'code', 'video_url', 'target_url', 'text', 'devices', 'category_ids', 'location_ids',
        'start_at', 'end_at', 'priority', 'max_impressions', 'max_clicks', 'status', 'created_by', 'updated_by'];

    public const TYPES = ['image' => 'इमेज / बैनर', 'html' => 'HTML कोड', 'adsense' => 'AdSense / Ad Manager', 'video' => 'वीडियो', 'link' => 'टेक्स्ट लिंक'];
    public const DEVICES = ['all' => 'सभी डिवाइस', 'desktop' => 'सिर्फ़ डेस्कटॉप', 'mobile' => 'सिर्फ़ मोबाइल'];
    public const STATUSES = ['active' => 'चालू', 'paused' => 'रुका', 'draft' => 'ड्राफ़्ट'];
    /** कच्चा कोड वाले प्रकार: ads.manage चाहिए */
    public const CODE_TYPES = ['html', 'adsense'];
}
