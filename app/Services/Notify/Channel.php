<?php
declare(strict_types=1);

namespace App\Services\Notify;

/** एक चैनल (ईमेल/पुश/SMS/WhatsApp): true = भेजा, 'gone' = पता हमेशा के लिए बेकार, और कोई string = त्रुटि (दोबारा कोशिश) */
interface Channel
{
    public function send(string $recipient, array $message): bool|string;
}
