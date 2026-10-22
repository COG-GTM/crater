<?php

use Illuminate\Mail\MailManager;

function loadMailConfigWithPort(?string $port): array
{
    if ($port === null) {
        putenv('MAIL_PORT');
        unset($_ENV['MAIL_PORT'], $_SERVER['MAIL_PORT']);
    } else {
        putenv("MAIL_PORT={$port}");
        $_ENV['MAIL_PORT'] = $port;
        $_SERVER['MAIL_PORT'] = $port;
    }

    return require config_path('mail.php');
}

afterEach(function () {
    loadMailConfigWithPort(null);
});

test('mail port from env is cast to an integer', function () {
    expect(loadMailConfigWithPort('2525')['port'])->toBe(2525);
});

test('empty mail port falls back to 587', function () {
    expect(loadMailConfigWithPort('')['port'])->toBe(587);
});

test('smtp transport builds with a port saved from mail settings', function () {
    config(['mail' => array_merge(loadMailConfigWithPort('2525'), [
        'driver' => 'smtp',
        'host' => '127.0.0.1',
        'encryption' => null,
    ])]);

    $transport = (new MailManager(app()))->mailer('smtp')->getSymfonyTransport();

    expect((string) $transport)->toContain('127.0.0.1:2525');
});
