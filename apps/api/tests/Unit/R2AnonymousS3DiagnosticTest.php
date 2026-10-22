<?php

namespace Tests\Unit;

use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;
use Tests\Support\R2AnonymousS3Diagnostic;

class R2AnonymousS3DiagnosticTest extends TestCase
{
    public function test_only_matching_authorization_errors_count_as_denial(): void
    {
        foreach ([
            [400, 'InvalidArgument', 'INCONCLUSIVE'],
            [404, 'NoSuchKey', 'INCONCLUSIVE'],
            [403, 'SignatureDoesNotMatch', 'INCONCLUSIVE'],
            [403, null, 'INCONCLUSIVE'],
            [403, 'AccessDenied', 'DENIED'],
            [401, 'Unauthorized', 'DENIED'],
            [200, null, 'EXPOSED_OR_REDIRECT'],
            [302, null, 'EXPOSED_OR_REDIRECT'],
        ] as [$status, $code, $verdict]) {
            $body = $code === null ? '' : '<Error><Code>'.$code.'</Code></Error>';
            $response = new Response(new PsrResponse($status, ['Content-Type' => $code === null ? 'text/plain' : 'application/xml'], $body));
            $this->assertSame($verdict, R2AnonymousS3Diagnostic::classify($response)['verdict']);
        }
    }
}
