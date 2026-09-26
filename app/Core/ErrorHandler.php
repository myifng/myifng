<?php
declare(strict_types=1);

namespace App\Core;

/**
 * सभी त्रुटियाँ यहाँ पकड़ी जाती हैं। प्रोडक्शन में सिर्फ़ साफ़ त्रुटि पेज, विवरण लॉग फ़ाइल में।
 * DEBUG चालू हो तो डेवलपर के लिए पूरा विवरण।
 */
final class ErrorHandler
{
    public function __construct(private Logger $logger, private bool $debug, private string $viewDir)
    {
    }

    public function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', $this->debug ? '1' : '0');
        set_error_handler(function (int $no, string $str, string $file, int $line): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
        set_exception_handler(fn(\Throwable $e) => $this->handle($e));
        register_shutdown_function(function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->handle(new \ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
            }
        });
    }

    public function handle(\Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status : 500;
        if ($status >= 500) {
            $this->logger->error(get_class($e) . ': ' . $e->getMessage(), ['file' => $e->getFile() . ':' . $e->getLine(), 'url' => $_SERVER['REQUEST_URI'] ?? '']);
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $this->render($status, $e)->send();
    }

    public function render(int $status, ?\Throwable $e = null): Response
    {
        $wantsJson = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json')
            || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
        $info = self::messages()[$status] ?? self::messages()[500];
        if ($e instanceof HttpException && $e->getMessage() !== '') {
            $info['message'] = $e->getMessage();
        }
        if ($wantsJson) {
            return Response::json(['ok' => false, 'message' => $info['message']], $status);
        }
        $debug = $this->debug && $e && $status >= 500 ? $e : null;
        try {
            $html = (new View($this->viewDir))->render('errors/error', ['status' => $status, 'title' => $info['title'], 'message' => $info['message'], 'exception' => $debug]);
        } catch (\Throwable) {
            $html = '<!doctype html><meta charset="utf-8"><title>' . $status . '</title><h1>' . $status . ' — ' . htmlspecialchars($info['title']) . '</h1><p>' . htmlspecialchars($info['message']) . '</p>';
        }
        $r = new Response($html, $status);
        if ($status === 503) {
            $r->header('Retry-After', '3600');
        }
        return $r;
    }

    public static function messages(): array
    {
        return [
            403 => ['title' => 'अनुमति नहीं है', 'message' => 'आपको यह पेज देखने या यह काम करने की अनुमति नहीं है। ज़रूरत हो तो एडमिन से संपर्क करें।'],
            404 => ['title' => 'पेज नहीं मिला', 'message' => 'यह पेज मौजूद नहीं है या हटा दिया गया है। पता जाँचें या होम पेज पर जाएँ।'],
            405 => ['title' => 'यह तरीका मान्य नहीं', 'message' => 'इस पते पर यह अनुरोध नहीं भेजा जा सकता।'],
            419 => ['title' => 'पेज की समय-सीमा ख़त्म', 'message' => 'सुरक्षा के लिए यह फ़ॉर्म पुराना हो गया है। पेज रीफ़्रेश करें और दोबारा भेजें।'],
            429 => ['title' => 'बहुत ज़्यादा प्रयास', 'message' => 'थोड़ी देर रुककर दोबारा कोशिश करें।'],
            500 => ['title' => 'सर्वर में गड़बड़ी', 'message' => 'कुछ ग़लत हो गया। हमने इसे दर्ज कर लिया है। थोड़ी देर बाद कोशिश करें।'],
            503 => ['title' => 'रखरखाव चल रहा है', 'message' => 'वेबसाइट पर अभी रखरखाव का काम चल रहा है। कृपया थोड़ी देर बाद आएँ।'],
        ];
    }
}
