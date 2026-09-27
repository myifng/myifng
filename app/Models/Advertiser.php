<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Advertiser extends Model
{
    protected static string $table = 'advertisers';
    protected static array $fillable = ['company', 'contact_name', 'email', 'phone', 'gstin', 'address', 'city', 'status', 'notes', 'user_id', 'created_by'];

    public const STATUSES = ['lead' => 'लीड', 'active' => 'चालू', 'inactive' => 'बंद'];
}
