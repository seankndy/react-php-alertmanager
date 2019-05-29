<?php
/**
 * Send email derived from ENV variables via SwiftMailer.
 * Used by react/child-process because I am currently
 * too lazy to write a socket-based SMTP client.
 *
 */

$autoload = [
    __DIR__.'/../../../../vendor/autoload.php',
    __DIR__.'../../vendor/autoload.php',
];
foreach ($autoload as $file) {
    if (\file_exists($file)) {
        require_once($file);
        break;
    }
}

if (!isset($_SERVER['server'], $_SERVER['port'], $_SERVER['from'],
    $_SERVER['to'], $_SERVER['message'])) {
    exit(1);
}
$to = preg_split('/[,;]\s*/', $_SERVER['to']);

$transport = new Swift_SmtpTransport($_SERVER['server'], $_SERVER['port']);
if (isset($_SERVER['username'], $_SERVER['password'])) {
    $transport->setUsername($_SERVER['username']);
    $transport->setPassword($_SERVER['password']);
}

$mailer = new Swift_Mailer($transport);

$message = (new Swift_Message(isset($_SERVER['subject']) ? $_SERVER['subject'] : ''))
    ->setFrom([$_SERVER['from']])
    ->setTo($to)
    ->setBody($_SERVER['message']);

$mailer->send($message);
