# react-php-alertmanager

AlertManager is a single-threaded aysnc IO alert manager written in PHP (using reactphp for async IO).

It receives externally-generated alerts/incidents from a collector via a simple HTTP API (JSON) and routes them to receivers.
Receivers are PHP objects that extend `\SeanKndy\AlertManager\Receivers\AbstractReceiver` or most basic form of a Receiver would implement
the `\SeanKndy\AlertManager\Receivers\ReceivableInterface` interface.  Included with AlertManager is an Email receiver, but it's very simple
to write your own receivers for PagerDuty, a database, etc...

## HTTP JSON API - Alert Format

```json
{
    "name":"unique.alert.name",
    "expiryDuration":600,
    "createdAt":1556153572,
    "state": "ACTIVE",
    "attributes":{
        "attr1":"value",
        "attr2":"value"
    }
}
```

'name' should be a unique name for the alert, but should stay consistent between submissions if it's the same incident.

'expiryDuration' is how long before the alert is auto-expired if its not updated.

'state' is optional and defaults to `ACTIVE`.  It can be `ACTIVE` or `RECOVERED`.

'createdAt' is optional and is when the alert originally fired.  If this is blank, the current time is used.

'attributes' is any number of key/value pairs and is specific to your environment. Routes use information within attributes to make decisions (see below).

AlertManager expects your collector to continually send the incident/alert into it as long as that incident is still active.
When the incident clears/service is green, then you can either stop submitting the alert and in expiryDuration seconds it will
auto-resolve, or you can submit the alert with status set to `RECOVERED` to expire/recover the alert immediately.

## Basic Usage

```php
<?php
use SeanKndy\AlertManager\Server;
use SeanKndy\AlertManager\Routing\Route;
use SeanKndy\AlertManager\Routing\Router;
use SeanKndy\AlertManager\Routing\Group;
use SeanKndy\AlertManager\Receivers\Email;

$loop = \React\EventLoop\Factory::create();

$smtpConfig = [
    'server' => 'localhost',
    'port' => 25',
    'active_from' => 'down@someserver.com', // from address when alert is ACTIVE
    'recovery_from' => 'recovered@someserver.com' // from address when alert is RECOVERED
];

$colin = new Email($loop, 'colin@somecompany.com', $smtpConfig);
$sean = new Email($loop, 'sean@somecompany.com', $smtpConfig);
$rob = new Email($loop, 'rob@somecompany.com', $smtpConfig);
$levi = new Email($loop, 'levi@somecompany.com', $smtpConfig);

//
// A Route destination can be anything that implements RoutableInterface
// or even NULL to essentially discard alerts.  This means you can route
// to receivers or even other Routers.
//
$router = new Router();
$router->addRoute(
    Route::toDestination($levi)->where('tag', 'servers')
);
$router->addRoute(
    Route::toDestination($sean)->where('tag', 'routers')
);
$router->addRoute(
    Route::toDestination($colin)->where('tag', 'wireless')
);
$router->addRoute(
    Route::toDestination($rob)->where('tag', 'switching')
);

$server = new \SeanKndy\AlertManager\Server('0.0.0.0:8514', $loop, $router);
$loop->run();
```

## Route Groups

If you want to route to a group of receivers, use a `SeanKndy\AlertManager\Routing\Group`:

```php
use SeanKndy\AlertManager\Routing\Route;
use SeanKndy\AlertManager\Routing\Router;
use SeanKndy\AlertManager\Routing\Group;
use SeanKndy\AlertManager\Receivers\Email;

$colin = new Email($loop, 'colin@somecompany.com', $smtpConfig);
$sean = new Email($loop, 'sean@somecompany.com', $smtpConfig);
$rob = new Email($loop, 'rob@somecompany.com', $smtpConfig);
$levi = new Email($loop, 'levi@somecompany.com', $smtpConfig);

$networkGroup = new Group([$sean, $rob]);
$serverGroup = new Group([$colin, $levi]);

$router->addRoute(
    Route::toDestination($serverGroup)->where('tag', 'servers')
);
$router->addRoute(
    Route::toDestination($networkGroup)->where('tag', ['routers','switching'])
);
```

## Conditional Logic Groups

You can create complex logical grouping on your Route where() statements by using an anonymous function:

```php
$router->addRoute(
    // will produce logic of:  (tag='servers' AND (cluster IN(24,25) or os='vmware'))
    Route::toDestination($receiver)
        ->where('tag', 'servers')
        ->where(function($criteria) {
            return $criteria->where('cluster', [24,25])
                ->orWhere('os', 'vmware');
        })
    )
);
```

You can continuing nesting where/orWheres to create your logical structures.

## Continuable Routes

By default, when a route is matched and an alert is routed, any routes below it will not be evaluated.  You can change
this behavior by using continue():

```php
$router->addRoute(
    Route::toDestination($receiver)->where('tag', 'servers')
)->continue();
$router->addRoute(
    Route::toDestination($receiver)->where('tag', 'network')
);
```

You can also issue a series of continue() calls followed by a stop() to stop the continuable route-chain but only if a route was actually routed to.  In other words, if any of the continued routes actually route, then stop at the stop().  If no routes matched, stop() does nothing.

## Alert Throttling

Included is a Throttler decorator class which will throttle alerts to any receiver after a threshold is reached within an interval:

```php
use SeanKndy\AlertManager\Routing\Route;
use SeanKndy\AlertManager\Receivers\Email;
use SeanKndy\AlertManager\Receivers\Throttler;

$sean = new Email($loop, 'sean@somecompany.com', $smtpConfig);
$throttledSean = new Throttler($sean);
$throttledSean->setHitThreshold(10); // after 10 alerts
$throttledSean->setInterval(60);     // ...within 60sec time
$throttledSean->setHoldDown(1800);   // hold down/throttle for 30min
$throttledSean->setOnHoldDownReceiver($sean); // send a ThrottledReceiverAlert
                                              // to the receiver $sean so the Receiver
                                              // can at least report that it has been throttled.
$router->addRoute(
    Route::toDestination($throttledSean)
);
```

## Receiver Scheduling

Any receivers that extend AbstractReceiver can have a time-based schedule to receive alerts.  You can use addSchedule() or setSchedules() to provide any number of `\SeanKndy\AlertManager\Scheduling\ScheduleInterface` implementations. `\SeanKndy\AlertManager\Scheduling\BasicScheduler` is a simple implementation provided with the package that you can use, or you can easily write your own scheduling logic by implementing the ScheduleInterface.  It's just one method: `isActive($atTime) : bool`.

## Receiver Filtering

Similar to scheduling, receivers can filter alerts as well.  AbstractReceiver provides an addFilter() method where you can specify a `\SeanKndy\AlertManager\Receivers\FilterInterface` implementation object.
