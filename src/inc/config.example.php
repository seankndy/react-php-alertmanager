<?php
return [
    'server_name' => 'MyServerName',
    'timezone' => 'America/Denver', // should match your DB's timezone
    'log_file' => 'php://stdout',
    'min_log_level' => \Psr\Log\LogLevel::INFO, // set to LogLevel::DEBUG to get verbose logging
    'pidfile' => '/var/run/cmpollerd.pid',
    'max_concurrent_checks' => 100,
    'check_pool_refresh_interval' => 60,

    'commands' => [
        'CheckPing' => \DI\autowire(\VCN\CMPollerD\Commands\Ping::class)
            ->constructorParameter('fpingBin', '/usr/bin/fping'),
        'CheckSMTP' => \DI\autowire(\VCN\CMPollerD\Commands\SMTP::class),
        'CheckSnmp' => \DI\autowire(\VCN\CMPollerD\Commands\SNMP::class)
            ->constructorParameter('snmpGetBin', '/usr/bin/snmpget'),
        'CheckCiscoResources' => \DI\autowire(\VCN\CMPollerD\Commands\CiscoResources::class)
            ->constructorParameter('snmpGetBin', '/usr/bin/snmpget'),
        'CheckDNS' => \DI\autowire(\VCN\CMPollerD\Commands\DNS::class),
        'CheckHTTP' => \DI\autowire(\VCN\CMPollerD\Commands\HTTP::class),
        'CheckMySQL' => \DI\autowire(\VCN\CMPollerD\Commands\MySQL::class)
    ],
    'handlers' => [
        'RRDCacheD' => \DI\autowire(\VCN\CMPollerD\Results\Handlers\RRDCacheD::class)
            ->constructorParameter('rrdDir', '/mnt/circuitsmngr/rrd')
            ->constructorParameter('rrdToolBin', '/usr/bin/rrdtool')
            ->constructorParameter('rrdCachedSockFile', '/var/run/rrdcached.sock')
    ]
];
