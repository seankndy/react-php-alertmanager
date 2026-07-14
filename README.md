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

Any receivers that extend AbstractReceiver can have a time-based schedule to receive alerts.  You can use addSchedule() or setSchedules() to provide any number of `\SeanKndy\AlertManager\Scheduling\ScheduleInterface` implementations. It's just one method: `public function isActive($atTime) : bool` and should return true if the given schedule is active/in effect at the timestamp $atTime, otherwise false.

A receiver's schedules are OR'd together, and its *exclusion* schedules veto them. A receiver with no schedules at all is always active.

The package ships three things you can use:

* **`RecurringSchedule`** and friends, for plain recurring windows ("weeknights", "business hours").
* **`Scheduling\OnCall`**, a full on-call rotation system. See below.
* **`BasicSchedule`**, deprecated. It can only express a single window repeating from a fixed anchor, which means a rotation has to be encoded as N staggered schedules — and one person's vacation then forces all N to be rewritten. Its `isActive()` also walks every repetition since the anchor, so it slows down as the anchor recedes into the past. Use the on-call classes instead.

### Recurring windows

`RecurringSchedule` is wall-clock based rather than anchored to a timestamp, so it never drifts and it survives DST. A window may wrap past midnight, which is how you say "overnight":

```php
use SeanKndy\AlertManager\Scheduling\{RecurringSchedule, DateRangeSchedule, AnyOf, AllOf, Not};

// Mon–Fri, 17:00 until 08:00 the next morning
$weeknights = RecurringSchedule::weekdays('17:00', '08:00', 'America/Denver');
$weekends   = RecurringSchedule::weekends('America/Denver');

// nights and weekends, but never on the 4th of July
$afterHours = new AllOf([
    new AnyOf([$weeknights, $weekends]),
    new Not(new DateRangeSchedule($julyFourthStart, $julyFourthEnd)),
]);
```

`AnyOf`, `AllOf`, `Not`, `AlwaysActive` and `NeverActive` compose any schedules, including your own.

## On-Call Rotations

A rotation is three facts and nothing else: **who is in it, in what order**; **how long a turn lasts**; and **when the first turn began**. Everything else is derived, so who's up at time T is a modulus rather than a search — the same cost whether T is tomorrow or in 2040.

```php
use SeanKndy\AlertManager\Scheduling\OnCall\{OnCallSchedule, Rotation, Layer, Override, ShiftLength, ParticipantSchedule};

// three techs, week about, handing off Mondays at 08:00
$rotation = new Rotation(
    ['alice', 'bob', 'carl'],
    ShiftLength::weeks(1),
    (new DateTime('2026-01-05 08:00', new DateTimeZone('America/Denver')))->getTimestamp(),
    'America/Denver'
);
```

Shift length is arbitrary — `ShiftLength::weeks(1)`, `days(1)`, `hours(12)` — so day/night splits and daily rotations fall out of the same code. Handoffs are laid out on the wall clock, so an 08:00 handoff stays at 08:00 across a DST transition instead of drifting to 07:00. A turn can be held by more than one person: `new Rotation([['alice', 'bob'], ['carl', 'dave']], ...)`.

**Layers** stack a rotation with the conditions under which it applies. The highest-priority layer that has anybody on call wins outright — layers do not union. Give a schedule a low-priority catch-all and it can never have a coverage hole:

```php
$fieldTechs = (new OnCallSchedule('Field Techs'))
    ->addLayer(new Layer('fallback',    Rotation::fixed(['noc'], $tz), null,        0))
    ->addLayer(new Layer('after hours', $rotation,                    $afterHours, 20));

$fieldTechs->participantsAt($tuesdayMorning);  // ['noc']    -- after-hours layer doesn't apply
$fieldTechs->participantsAt($tuesdayEvening);  // ['alice']  -- it does, and masks the fallback
```

**Overrides** are the reason this exists. One person covering another for a window is a single fact laid over the top of the rotation — the rotation itself is not touched, and deleting the override restores it exactly:

```php
// Alice is out next week. Bob covers.
$fieldTechs->addOverride(new Override('bob', 'alice', $mondayEight, $nextMondayEight, 'PTO'));
```

The covering person doesn't have to be in the rotation at all.

**Plugging it into a receiver.** An `OnCallSchedule` answers *"who is on call?"*; a receiver needs *"am I on call?"*. `ParticipantSchedule` is the adapter, and it's the only part of this the rest of AlertManager needs to know about:

```php
$aliceReceiver->addSchedule(new ParticipantSchedule($fieldTechs, 'alice'));
```

**Querying it.** `participantsAt($t)`, `isOnCall($who, $t)`, `shiftAt($t)`, `nextChangeAfter($t)` for handoff notifications, and `timeline($from, $to)` for a calendar view (contiguous blocks, with runs of the same people merged).

**Persistence.** AlertManager holds no state, so every one of these objects round-trips through `toArray()` / `fromArray()`, and restrictions go through `ScheduleFactory` (`fromJson()` takes a nullable column straight from the database). Store the rotation as an order plus a shift length plus an anchor, rebuild the objects on each refresh, and throw them away.

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
