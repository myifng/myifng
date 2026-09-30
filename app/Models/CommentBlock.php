<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class CommentBlock extends Model
{
    protected static string $table = 'comment_blocks';
    protected static bool $timestamps = false;
    protected static array $fillable = ['type', 'value', 'note', 'created_by', 'created_at'];

    public const TYPES = ['email' => 'ईमेल', 'ip' => 'IP (हैश)', 'reader' => 'पाठक खाता', 'word' => 'शब्द'];
}
