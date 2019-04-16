<?php
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Monolog\Logger;
use Monolog\Handler\SyslogHandler;
use Monolog\Handler\StreamHandler;
use React\EventLoop\LoopInterface;

error_reporting(E_ALL);
set_time_limit(0);

require_once __DIR__.'/../../vendor/autoload.php';
if (!file_exists(__DIR__.'/../../src/inc/config.php')) {
    die("ERROR: config file '".__DIR__."/../../src/inc/config.php' is required, but missing!\n");
}
$config = include __DIR__ . '/../../src/inc/config.php';

// set timezone
date_default_timezone_set($config['timezone']);

// initialize DI container with key object definitions
$containerBuilder = new \DI\ContainerBuilder();
$containerBuilder->addDefinitions([
    //
    // Our EventLoop
    //
    LoopInterface::class => function (ContainerInterface $c) {
        return \React\EventLoop\Factory::create();
    },
    //
    // Logger
    //
    LoggerInterface::class => function (ContainerInterface $c) {
        try {
            $logger = new Logger('cmpollerd', [
                new StreamHandler($c->get('config')['log_file'], $c->get('config')['min_log_level'])
            ]);
            $logger->pushProcessor(function ($record) {
                $record['message'] = '[PID:' . \getmypid() . '] ' . $record['message'];
                return $record;
            });
        } catch (\Exception $e) {
            $logger = new NullLogger();
        }
        return $logger;
    },
]);
$container = $containerBuilder->build();
$container->set('config', $config);
