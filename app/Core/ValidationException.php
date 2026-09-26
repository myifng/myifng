<?php
declare(strict_types=1);

namespace App\Core;

/** वैलिडेशन फ़ेल: फ़ॉर्म पर त्रुटियों के साथ वापस भेजने के लिए */
final class ValidationException extends \RuntimeException
{
    public function __construct(public readonly array $errors, public readonly array $input = [])
    {
        parent::__construct('वैलिडेशन त्रुटि');
    }
}
