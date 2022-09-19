# react-php-alertmanager

AlertManager is a single-threaded aysnc IO alert manager written in PHP (using reactphp for async IO).

It receives externally-generated alerts/incidents from a collector via a simple HTTP API (JSON) and routes them to receivers.
Receivers are PHP objects that extend `\SeanKndy\AlertManager\Receivers\AbstractReceiver` or most basic form of a Receiver would implement
the `\SeanKndy\AlertManager\Receivers\ReceivableInterface` interface.  `AbstractReceiver` provides some commonly desired functionality like
scheduling, filtering, initial delay and whether or not to receive recovered alerts. Included with AlertManager is an Email receiver and a
basic Slack receiver, but it's very straightforward to write your own receivers such as to PagerDuty, a database, whatever...

This is similar to Prometheus' Alertmanager, however it's obviously written in PHP and is far simpler.  I wrote it because the rest of my monitoring
infrastructure is written in PHP as well and I wanted the ability to very easily extend functionality in this environment.  Because the routing is defined
within PHP, it's very simple to programmatically build your routes from any source (be it flat file like Prometheus, a database, or whatever).  Also, the
ability to do receiver scheduling, filtering, null routing, and simply how the routing logic is done I believe is quite different from Prometheus and better
suits my needs.

## HTTP JSON API - Alert Format

JSON should be POSTed to http://x.x.x.x:port/api/v1/alerts in the following format:

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

'expiryDuration' is optional and will default to whatever `\SeanKndy\AlertManager\Alerts\Alert::$defaultExpiryDuration` is set to.  It is how long before the alert is auto-expired if it has not updated.

'state' is optional and defaults to `ACTIVE`.  It can be `ACTIVE`, `INACTIVE`, `ACKNOWLEDGED` or `RECOVERED`.

'createdAt' is optional and is when the alert originally fired.  If this is blank, the current time is used.

'attributes' is any number of key/value pairs and is specific to your environment. Routes use information within attributes to make decisions (see below).

You can also submit multiple alerts at once by putting each alert within an array and submitting that JSON-encoded.


AlertManager expects your collector to continually send the incident/alert into it as long as that incident is still active.
When the incident clears/service is green, then you can either stop submitting the alert and in expiryDuration seconds it will
auto-resolve, or you can submit the alert with status set to `RECOVERED` to expire/recover the alert immediately.

## Basic Usage

```php
<?php
use SeanKndy\AlertManager\Alerts\Processor;
use SeanKndy\AlertManager\Routing\Route;
use SeanKndy\AlertManager\Routing\Router;
use SeanKndy\AlertManager\Receivers\Email;

$loop = \React\EventLoop\Factory::create();

$smtpConfig = [
    'server' => 'localhost',
    'port' => 25,
    'active_from' => 'down@someserver.com', // from address when alert is ACTIVE
    'recovery_from' => 'recovered@someserver.com' // from address when alert is RECOVERED
];

$alertTemplate = SomeAlertTemplate(); // implements Alerts\TemplateInterface

$colin = new Email('colin', $loop, 'colin@somecompany.com', $smtpConfig);
$sean = new Email('sean', $loop, 'sean@somecompany.com', $smtpConfig);
$rob = new Email('rob', $loop, 'rob@somecompany.com', $smtpConfig);
$levi = new Email('levi', $loop, 'levi@somecompany.com', $smtpConfig);

$colin->setAlertTemplate($alertTemplate);
$sean->setAlertTemplate($alertTemplate);
$rob->setAlertTemplate($alertTemplate);
$levi->setAlertTemplate($alertTemplate);

//
// A Route destination can be anything that implements RoutableInterface
// or even NULL to essentially discard alerts.  This means you can route
// to receivers or even other Routers.
//
$router = (new Router())->addRoutes([
    Route::toDestination($levi)->where('tag', 'servers'),
    Route::toDestination($sean)->where('tag', 'routers'),
    Route::toDestination($colin)->where('tag', 'wireless'),
    Route::toDestination($rob)->where('tag', 'switching')
]);

$server = new \SeanKndy\AlertManager\Http\Server(
    $loop,
    '0.0.0.0:8514',
    $router
);
$loop->run();
```

## Route Groups

If you want to route to a group of receivers, use a `SeanKndy\AlertManager\Routing\Group`:

```php
use SeanKndy\AlertManager\Routing\Route;
use SeanKndy\AlertManager\Routing\Router;
use SeanKndy\AlertManager\Receivers\Group;
use SeanKndy\AlertManager\Receivers\Email;

$colin = new Email('colin', $loop, 'colin@somecompany.com', $smtpConfig);
$sean = new Email('sean', $loop, 'sean@somecompany.com', $smtpConfig);
$rob = new Email('rob', $loop, 'rob@somecompany.com', $smtpConfig);
$levi = new Email('levi', $loop, 'levi@somecompany.com', $smtpConfig);

$networkGroup = new Group([$sean, $rob]);
$serverGroup = new Group([$colin, $levi]);

$router = (new Router())->addRoutes([
    Route::toDestination($serverGroup)->where('tag', 'servers'),
    Route::toDestination($networkGroup)->where('tag', ['routers','switching'])
]);
```

## Conditional Logic Groups

You can create complex logical grouping on your Route where() statements by using an anonymous function:

```php
$router->addRoute(
    // will produce logic of:  (tag='servers' AND (cluster IN(24,25) OR os='vmware'))
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
    Route::toDestination($receiverA)->where('tag', 'servers')
)->continue();
$router->addRoute(
    Route::toDestination($receiverA)->where('tag', 'network')
);
```

This would be similar to saying `Route::toDestination($receiverA)->where('tag', ['servers','network'])`.

You can also issue a series of continue() calls followed by a stop() to stop the continuable route-chain but only if a route was actually routed to.  In other words, if any of the continued routes actually route, then stop at the stop().  If no routes matched, stop() does nothing.

This is useful if you have a hierarchy of routes and you want any of the routes within the same level to be tested/routed and if anything in that level routes, you want to stop.  If nothing matched at that level, you want to move on to the next level below it.   In this case, every route in the same level would have continue()s except the last route on that level which would have a stop().

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

Any receivers that extend AbstractReceiver can have a time-based schedule to receive alerts.  You can use addSchedule() or setSchedules() to provide any number of `\SeanKndy\AlertManager\Scheduling\ScheduleInterface` implementations. `\SeanKndy\AlertManager\Scheduling\BasicScheduler` is a simple implementation provided with the package that you can use, or you can easily write your own scheduling logic by implementing the ScheduleInterface.  It's just one method: `public function isActive($atTime) : bool` and should return true if the given schedule is active/in effect at the timestamp $atTime, otherwise false.

## Receiver Filtering

Similar to scheduling, receivers can filter alerts as well.  AbstractReceiver provides an addFilter() method where you can specify a `\SeanKndy\AlertManager\Receivers\FilterInterface` implementation object.  Quick example:

```php
use SeanKndy\AlertManager\Alerts\FilterInterface;
use SeanKndy\AlertManager\Receivers\Email;

$sean = new Email($loop, 'sean@somecompany.com', $smtpConfig);
$sean->addFilter(new class implements FilterInterface {
    public function isFiltered(Alert $alert) : bool {
        $attributes = $alert->getAttributes();
        // i only want alerts with location == Wyoming
        return ($attributes['location'] == 'Wyoming');
    }
});
```

Note that both Scheduling and Filtering expect simple boolean return values, so they cannot do any IO or long-running operations as that will block the process. The Receivers use a promise-based interface so that they may perform longer IO ops sending mail, connecting to pager services, etc...


## Alert Preprocessing

An alert preprocessor gives you the ability to process or act on every Alert prior to it being queued to the server.  A preprocessor has the ability to mutate any alert prior to queueing.  Every preprocessor pushed to the server will run in the order they were pushed, one after the next.

You may develop your own alert pre-processors by implementing `\SeanKndy\AlertManager\Preprocessors\PreprocessorInterface` and then tell the alert processor about it with `$alertProcess->pushPreprocessor($preprocessor)`.
