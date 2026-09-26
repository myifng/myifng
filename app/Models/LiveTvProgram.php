<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class LiveTvProgram extends Model
{
    protected static string $table = 'live_tv_programs';
    protected static array $fillable = ['channel_id', 'title', 'host', 'description', 'days', 'start_time', 'end_time', 'status'];

    /** PHP date('w'): 0 = रविवार */
    public const DAYS = [1 => 'सोम', 2 => 'मंगल', 3 => 'बुध', 4 => 'गुरु', 5 => 'शुक्र', 6 => 'शनि', 0 => 'रवि'];
}
