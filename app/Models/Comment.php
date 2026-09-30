<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Comment extends Model
{
    protected static string $table = 'comments';
    protected static array $fillable = ['news_id', 'parent_id', 'reader_id', 'user_id', 'name', 'email', 'body', 'status', 'ip_hash', 'user_agent', 'approved_by'];

    public const STATUSES = ['pending' => 'पेंडिंग', 'approved' => 'स्वीकृत', 'spam' => 'स्पैम', 'rejected' => 'अस्वीकृत'];
    public const BADGE = ['pending' => 'warning', 'approved' => 'success', 'spam' => 'dark', 'rejected' => 'secondary'];
}
