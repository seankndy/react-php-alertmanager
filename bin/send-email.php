<?php
/**
 * Send email derived from ENV variables via SwiftMailer.
 * Used by react/child-process because I am currently
 * too lazy to write a socket-based SMTP client.
 *
 */

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;

$autoload = [
    __DIR__.'/../../../../vendor/autoload.php',
    __DIR__.'../../vendor/autoload.php',
    __DIR__.'/../vendor/autoload.php',
];
foreach ($autoload as $file) {
    if (\file_exists($file)) {
        require_once($file);
        break;
    }
}

if (!isset($_SERVER['server'], $_SERVER['port'], $_SERVER['from'],
    $_SERVER['to'], $_SERVER['message'])) {
    fprintf(STDERR, "Required environment variables missing.");
    exit(1);
}
$to = preg_split('/[,;]\s*/', $_SERVER['to']);

$transport = new EsmtpTransport($_SERVER['server'], $_SERVER['port']);
if (isset($_SERVER['username'], $_SERVER['password'])) {
    $transport->setUsername($_SERVER['username']);
    $transport->setPassword($_SERVER['password']);
}

$mailer = new Mailer($transport);

$message = (new Email())
    ->from($_SERVER['from'])
    ->to(...$to)
    ->subject($_SERVER['subject'] ?? '')
    ->text($_SERVER['message']);

try {
    $mailer->send($message);
} catch (TransportExceptionInterface $e) {
    fprintf(STDERR, "Failed sending email: %s", $e->getMessage());
    exit(1);
}
