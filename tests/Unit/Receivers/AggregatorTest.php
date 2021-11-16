<?php

namespace Tests\Unit\Receivers;

use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use SeanKndy\AlertManager\Alerts\AggregatedAlert;
use SeanKndy\AlertManager\Alerts\Alert;
use SeanKndy\AlertManager\Receivers\AbstractReceiver;
use SeanKndy\AlertManager\Receivers\Aggregator;
use Spatie\TestTime\TestTime;

class AggregatorTest extends TestCase
{
    /** @test */
    public function it_does_not_send_underlying_receiver_alert_when_inside_an_interval_window()
    {
        $alert1 = new Alert('test1', Alert::ACTIVE, []);
        $alert2 = new Alert('test2', Alert::ACTIVE, []);
        $alert3 = new Alert('test3', Alert::ACTIVE, []);

        $mockReceiver = $this->createMock(AbstractReceiver::class);
        $mockReceiver->expects($this->any())->method('isReceivable')->willReturn(true);
        $mockReceiver->expects($this->never())->method('receive');

        $aggregator = new Aggregator($mockReceiver);
        $aggregator->setInterval(15);

        TestTime::freeze();

        $aggregator->route($alert1);

        TestTime::addMinute(5);

        $aggregator->route($alert2);

        TestTime::addMinute(8);

        $aggregator->route($alert3);
    }

    /** @test */
    public function it_sends_underlying_receiver_aggregated_alert_when_interval_ends()
    {
        $alert1 = new Alert('test1', Alert::ACTIVE, []);
        $alert2 = new Alert('test2', Alert::ACTIVE, []);
        $alert3 = new Alert('test3', Alert::ACTIVE, []);

        $mockReceiver = $this->createMock(AbstractReceiver::class);
        $mockReceiver->expects($this->any())->method('isReceivable')->willReturn(true);
        $mockReceiver->expects($this->once())->method('receive')->with($this->callback(function($arg) {
            return $arg instanceof AggregatedAlert && \array_map(fn($a) => $a->getName(), $arg->alerts) == [
                'test1',
                'test2',
                'test3'
            ];
        }));

        $aggregator = new Aggregator($mockReceiver);
        $aggregator->setInterval(15);

        TestTime::freeze();

        $aggregator->route($alert1);
        $aggregator->route($alert2);

        TestTime::addMinute(15);

        $aggregator->route($alert3);
    }

    /** @test */
    public function it_logs_receiver_dispatch_on_each_alert()
    {
        $alert1 = new Alert('test1', Alert::ACTIVE, []);
        $alert2 = new Alert('test2', Alert::ACTIVE, []);
        $alert3 = new Alert('test3', Alert::ACTIVE, []);

        $mockReceiver = $this->createMock(AbstractReceiver::class);
        $mockReceiver->expects($this->any())->method('isReceivable')->willReturn(true);
        $mockReceiver->expects($this->once())->method('receive')->with($this->callback(function($arg) {
            return $arg instanceof AggregatedAlert && \array_map(fn($a) => $a->getName(), $arg->alerts) == [
                    'test1',
                    'test2',
                    'test3'
                ];
        }));

        $aggregator = new Aggregator($mockReceiver);
        $aggregator->setInterval(15);

        TestTime::freeze();

        $aggregator->route($alert1);
        $aggregator->route($alert2);

        TestTime::addMinute(15);

        $aggregator->route($alert3);

        $this->assertEquals([
            Alert::ACTIVE => Carbon::now()->timestamp,
        ], $alert1->getDispatchLogForReceiver($mockReceiver));

        $this->assertEquals([
            Alert::ACTIVE => Carbon::now()->timestamp,
        ], $alert2->getDispatchLogForReceiver($mockReceiver));

        $this->assertEquals([
            Alert::ACTIVE => Carbon::now()->timestamp,
        ], $alert3->getDispatchLogForReceiver($mockReceiver));
    }

    /** @test */
    public function it_does_not_send_alert_that_became_unreceivable_within_the_interval_window()
    {
        $alert1 = new Alert('test1', Alert::ACTIVE, []);
        $alert2 = new Alert('test2', Alert::ACTIVE, []);
        $alert3 = new Alert('test3', Alert::ACTIVE, []);

        $mockReceiver = $this->createMock(AbstractReceiver::class);
        $mockReceiver->expects($this->any())->method('isReceivable')->will($this->onConsecutiveCalls(
            true,
            true,
            false,
            true
        ));
        $mockReceiver->expects($this->once())->method('receive')->with($this->callback(function($arg) {
            return $arg instanceof AggregatedAlert && \array_map(fn($a) => $a->getName(), $arg->alerts) == [
                    'test1',
                    'test3'
                ];
        }));

        $aggregator = new Aggregator($mockReceiver);
        $aggregator->setInterval(15);

        TestTime::freeze();

        $aggregator->route($alert1);
        $aggregator->route($alert2);
        $alert2->setState(Alert::RECOVERED);
        $aggregator->route($alert2); // isReceivable should return false on this

        TestTime::addMinute(15);

        $aggregator->route($alert3);
    }

    /** @test */
    public function it_does_not_send_empty_aggregated_alert()
    {
        $alert1 = new Alert('test1', Alert::ACTIVE, []);
        $alert2 = new Alert('test2', Alert::ACTIVE, []);
        $alert3 = new Alert('test3', Alert::ACTIVE, []);

        $mockReceiver = $this->createMock(AbstractReceiver::class);
        $mockReceiver->expects($this->any())->method('isReceivable')->will($this->onConsecutiveCalls(
            true,
            true,
            true,
            false,
            false,
            false
        ));
        $mockReceiver->expects($this->never())->method('receive');

        $aggregator = new Aggregator($mockReceiver);
        $aggregator->setInterval(15);

        TestTime::freeze();

        $aggregator->route($alert1);
        $aggregator->route($alert2);
        $aggregator->route($alert3);

        TestTime::addMinute(15);

        // at the end of 15 minutes, all these alerts have become unreceivable
        $aggregator->route($alert1);
        $aggregator->route($alert2);
        $aggregator->route($alert3);
    }
}
