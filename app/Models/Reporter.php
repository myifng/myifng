<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Reporter extends Model
{
    protected static string $table = 'reporters';
    protected static array $fillable = ['user_id', 'reporter_code', 'application_id', 'designation', 'reporter_type', 'beat_category_id', 'area_location_id',
        'district_id', 'state_id', 'bureau_id', 'joining_date', 'valid_until', 'status', 'photo', 'guardian_name', 'dob', 'mobile', 'blood_group', 'address',
        'kyc', 'notes', 'status_reason', 'created_by'];

    public const TYPES = [
        'staff' => 'स्टाफ़ रिपोर्टर', 'state' => 'राज्य संवाददाता', 'district' => 'ज़िला संवाददाता', 'tehsil' => 'तहसील संवाददाता',
        'city' => 'नगर संवाददाता', 'stringer' => 'स्ट्रिंगर', 'photo' => 'फ़ोटो जर्नलिस्ट', 'video' => 'वीडियो जर्नलिस्ट', 'freelance' => 'फ़्रीलांस',
    ];
    public const STATUSES = ['active' => ['सक्रिय', 'success'], 'suspended' => ['निलंबित', 'danger'], 'resigned' => ['इस्तीफ़ा', 'secondary'], 'expired' => ['वैधता समाप्त', 'warning']];
}
