<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Poll extends Model
{
    protected static string $table = 'polls';
    protected static array $fillable = ['question', 'description', 'multiple', 'show_results', 'require_login', 'status', 'start_at', 'end_at', 'total_votes', 'voters', 'created_by'];

    public const STATUSES = ['draft' => 'ड्राफ़्ट', 'active' => 'चालू', 'closed' => 'बंद'];
    public const RESULTS = ['after_vote' => 'वोट देने के बाद', 'always' => 'हमेशा', 'after_close' => 'पोल ख़त्म होने पर'];
}
