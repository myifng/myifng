<?php
declare(strict_types=1);

namespace App\Core;

/**
 * ईमेल भेजना। अभी PHP mail() से (ज़्यादातर shared hosting पर चलता है)।
 * भेज न पाए तो storage/logs/mail-*.log में दर्ज होता है। SMTP ड्राइवर आगे के phase में जुड़ेगा।
 */
final class Mailer
{
    public function __construct(private Logger $logger, private string $fromEmail, private string $fromName)
    {
    }

    public function send(string $to, string $subject, string $html): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . mb_encode_mimeheader($this->fromName, 'UTF-8') . ' <' . $this->fromEmail . '>',
            'X-Mailer: PHP',
        ];
        $ok = false;
        if (function_exists('mail') && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $ok = @mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $html, implode("\r\n", $headers));
        }
        $this->logger->log($ok ? 'info' : 'warning', ($ok ? 'भेजा: ' : 'नहीं भेजा जा सका: ') . $subject, ['to' => $to, 'body' => $ok ? null : strip_tags($html)], 'mail');
        return $ok;
    }
}
