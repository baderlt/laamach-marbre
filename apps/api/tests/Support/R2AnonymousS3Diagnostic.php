<?php

namespace Tests\Support;

use Illuminate\Http\Client\Response;

final class R2AnonymousS3Diagnostic
{
    public static function classify(Response $response): array
    {
        $status = $response->status();
        $contentType = $response->header('Content-Type');
        $body = $response->body();
        $code = null;
        if (strlen($body) <= 4096 && str_contains(strtolower($contentType ?? ''), 'xml')) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body, \SimpleXMLElement::class, LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            if ($xml !== false && $xml->getName() === 'Error') {
                $candidate = (string) $xml->Code;
                if (preg_match('/^[A-Za-z][A-Za-z0-9]{0,63}$/D', $candidate)) {
                    $code = $candidate;
                }
            }
        }

        $verdict = match (true) {
            $status >= 200 && $status < 400 => 'EXPOSED_OR_REDIRECT',
            $status === 401 && $code === 'Unauthorized', $status === 403 && $code === 'AccessDenied' => 'DENIED',
            default => 'INCONCLUSIVE',
        };

        return ['status' => $status, 'content_type' => $contentType, 's3_xml_code' => $code, 'verdict' => $verdict];
    }
}
