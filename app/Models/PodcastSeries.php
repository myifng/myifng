<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class PodcastSeries extends Model
{
    protected static string $table = 'podcast_series';
    protected static array $fillable = ['title', 'slug', 'description', 'cover', 'author', 'category_id', 'status', 'sort_order'];
}
