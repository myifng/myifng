<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ReporterDocument extends Model
{
    protected static string $table = 'reporter_documents';
    protected static array $fillable = ['reporter_id', 'type', 'doc_no', 'issued_at', 'valid_until', 'issued_by', 'status', 'revoked_reason', 'created_at'];
    protected static bool $timestamps = false;

    /** type => [नाम, नंबर का कोड] */
    public const TYPES = [
        'id_card' => ['ID कार्ड', 'IDC'], 'authorization' => ['अधिकार पत्र', 'AUTH'], 'appointment' => ['नियुक्ति पत्र', 'APT'],
        'press_certificate' => ['प्रेस प्रमाणपत्र', 'PRC'], 'experience' => ['अनुभव प्रमाणपत्र', 'EXP'],
    ];
}
