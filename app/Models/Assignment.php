<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Assignment extends Model
{
    protected static string $table = 'assignments';
    protected static array $fillable = ['title', 'description', 'reporter_id', 'category_id', 'location_id', 'priority', 'deadline', 'instructions',
        'attachments', 'status', 'news_id', 'created_by'];

    public const PRIORITIES = ['low' => ['कम', 'secondary'], 'normal' => ['सामान्य', 'info'], 'high' => ['ऊँची', 'warning'], 'urgent' => ['तुरंत', 'danger']];
    public const STATUSES = [
        'open' => ['नया', 'secondary'], 'accepted' => ['स्वीकार', 'info'], 'in_progress' => ['काम जारी', 'primary'],
        'submitted' => ['ख़बर भेजी', 'warning'], 'completed' => ['पूरा', 'success'], 'cancelled' => ['रद्द', 'dark'],
    ];
    /** रिपोर्टर ख़ुद ये स्थितियाँ चुन सकता है */
    public const REPORTER_STATUSES = ['accepted', 'in_progress'];
}
