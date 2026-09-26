<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class ReporterApplication extends Model
{
    protected static string $table = 'reporter_applications';
    protected static array $fillable = ['app_no', 'full_name', 'guardian_name', 'dob', 'gender', 'mobile', 'whatsapp', 'email', 'address', 'state_id', 'district_id',
        'city', 'pincode', 'preferred_area_id', 'reporter_type', 'experience_years', 'previous_org', 'education', 'languages', 'about', 'documents', 'status',
        'assigned_to', 'public_note', 'reporter_id', 'ip', 'consent_at'];

    public const STATUSES = [
        'new' => ['नया', 'info'], 'under_review' => ['समीक्षा में', 'primary'], 'document_pending' => ['दस्तावेज़ बाकी', 'warning'],
        'verification_pending' => ['सत्यापन बाकी', 'purple'], 'approved' => ['मंज़ूर', 'success'], 'rejected' => ['अस्वीकार', 'danger'], 'on_hold' => ['होल्ड', 'secondary'],
    ];
    /** आवेदक को दिखने वाला संदेश (स्थिति पेज) */
    public const PUBLIC_TEXT = [
        'new' => 'आपका आवेदन मिल गया है और जल्द समीक्षा होगी।',
        'under_review' => 'आपके आवेदन की समीक्षा चल रही है।',
        'document_pending' => 'कुछ दस्तावेज़ों में सुधार या नए दस्तावेज़ चाहिए। नीचे दिया संदेश देखें।',
        'verification_pending' => 'आपकी जानकारी का सत्यापन चल रहा है। हमारी टीम आपसे संपर्क कर सकती है।',
        'approved' => 'बधाई! आपका आवेदन मंज़ूर हो गया है। लॉगिन की जानकारी आपके ईमेल पर भेजी गई है।',
        'rejected' => 'खेद है, इस बार आपका आवेदन स्वीकार नहीं हो सका।',
        'on_hold' => 'आपका आवेदन अभी रोका गया है। आगे की सूचना दी जाएगी।',
    ];
    /** दस्तावेज़: key => [लेबल, ज़रूरी?] */
    public const DOCUMENTS = [
        'photo' => ['प्रोफ़ाइल फ़ोटो', true], 'id_proof' => ['पहचान पत्र (आधार/वोटर ID आदि)', true], 'address_proof' => ['पते का प्रमाण', true],
        'education' => ['शैक्षिक प्रमाणपत्र', false], 'experience' => ['अनुभव प्रमाणपत्र', false], 'press_card' => ['पिछला प्रेस कार्ड', false], 'signature' => ['हस्ताक्षर', true],
    ];
    public const GENDERS = ['male' => 'पुरुष', 'female' => 'महिला', 'other' => 'अन्य'];
}
