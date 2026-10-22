<?php

use Crater\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

afterEach(function () {
    Request::setTrustedProxies([], -1);
});

function forwardedHttpsRequest(string $remoteAddr): Request
{
    return Request::create('http://billing.example.com/login', 'GET', [], [], [], [
        'REMOTE_ADDR' => $remoteAddr,
        'HTTP_X_FORWARDED_PROTO' => 'https',
        'HTTP_X_FORWARDED_PORT' => '443',
    ]);
}

test('forwarded https from a trusted proxy is honoured by default', function () {
    $request = forwardedHttpsRequest('10.0.0.5');

    app(TrustProxies::class)->handle($request, fn () => response(''));

    expect($request->isSecure())->toBeTrue()
        ->and($request->getSchemeAndHttpHost())->toBe('https://billing.example.com');
});

test('forwarded headers from proxies outside TRUSTED_PROXIES are ignored', function () {
    config(['trustedproxy.proxies' => '192.168.1.1']);
    $request = forwardedHttpsRequest('10.0.0.5');

    app(TrustProxies::class)->handle($request, fn () => response(''));

    expect($request->isSecure())->toBeFalse();
});

test('legacy MAIL_DRIVER smtp settings build an smtp transport for the configured host', function () {
    config([
        'mail.driver' => 'smtp',
        'mail.host' => 'smtp.example.com',
        'mail.port' => 2525,
        'mail.encryption' => null,
        'mail.username' => 'mailer',
        'mail.password' => 'secret',
    ]);

    $transport = (new MailManager(app()))->mailer()->getSymfonyTransport();

    expect($transport)->toBeInstanceOf(EsmtpTransport::class)
        ->and($transport->getStream()->getHost())->toBe('smtp.example.com')
        ->and($transport->getStream()->getPort())->toBe(2525)
        ->and($transport->getUsername())->toBe('mailer');
});
