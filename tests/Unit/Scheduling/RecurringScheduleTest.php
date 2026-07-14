<?php

namespace Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;
use SeanKndy\AlertManager\Scheduling\AllOf;
use SeanKndy\AlertManager\Scheduling\AlwaysActive;
use SeanKndy\AlertManager\Scheduling\AnyOf;
use SeanKndy\AlertManager\Scheduling\DateRangeSchedule;
use SeanKndy\AlertManager\Scheduling\NeverActive;
use SeanKndy\AlertManager\Scheduling\Not;
use SeanKndy\AlertManager\Scheduling\RecurringSchedule;
use SeanKndy\AlertManager\Scheduling\ScheduleFactory;

class RecurringScheduleTest extends TestCase
{
    private const TZ = 'America/Denver';

    private function ts(string $when): int
    {
        return (new \DateTime($when, new \DateTimeZone(self::TZ)))->getTimestamp();
    }

    /** @test */
    public function it_is_active_within_a_same_day_window()
    {
        // Mon-Fri 08:00-17:00. Jan 6 2026 is a Tuesday.
        $schedule = RecurringSchedule::weekdays('08:00', '17:00', self::TZ);

        $this->assertFalse($schedule->isActive($this->ts('Jan 6 2026 07:59:59')));
        $this->assertTrue($schedule->isActive($this->ts('Jan 6 2026 08:00:00')));
        $this->assertTrue($schedule->isActive($this->ts('Jan 6 2026 16:59:59')));
        $this->assertFalse($schedule->isActive($this->ts('Jan 6 2026 17:00:00')));
    }

    /** @test */
    public function it_is_not_active_on_days_it_does_not_recur_on()
    {
        $schedule = RecurringSchedule::weekdays('08:00', '17:00', self::TZ);

        // Jan 10 2026 is a Saturday.
        $this->assertFalse($schedule->isActive($this->ts('Jan 10 2026 12:00:00')));
    }

    /** @test */
    public function a_window_can_wrap_past_midnight()
    {
        // Weeknights: starts Mon-Fri 17:00, runs until 08:00 the next morning.
        $schedule = RecurringSchedule::weekdays('17:00', '08:00', self::TZ);

        // Tuesday evening -- inside the window that opened Tuesday 17:00.
        $this->assertTrue($schedule->isActive($this->ts('Jan 6 2026 22:00:00')));

        // Wednesday 03:00 -- still inside Tuesday's window, which has wrapped.
        $this->assertTrue($schedule->isActive($this->ts('Jan 7 2026 03:00:00')));

        // Wednesday 07:59 -- last minute of Tuesday's window.
        $this->assertTrue($schedule->isActive($this->ts('Jan 7 2026 07:59:59')));

        // Wednesday 08:00 -- closed.
        $this->assertFalse($schedule->isActive($this->ts('Jan 7 2026 08:00:00')));

        // Wednesday noon -- between windows.
        $this->assertFalse($schedule->isActive($this->ts('Jan 7 2026 12:00:00')));
    }

    /** @test */
    public function a_wrapping_window_started_on_friday_runs_into_saturday()
    {
        $schedule = RecurringSchedule::weekdays('17:00', '08:00', self::TZ);

        // Jan 9 2026 is a Friday; Jan 10 is a Saturday.
        $this->assertTrue($schedule->isActive($this->ts('Jan 10 2026 03:00:00')));
        $this->assertFalse($schedule->isActive($this->ts('Jan 10 2026 09:00:00')));
    }

    /** @test */
    public function equal_start_and_end_times_mean_the_whole_day()
    {
        $schedule = RecurringSchedule::weekends(self::TZ);

        $this->assertTrue($schedule->isActive($this->ts('Jan 10 2026 00:00:00')));
        $this->assertTrue($schedule->isActive($this->ts('Jan 10 2026 23:59:59')));
        $this->assertTrue($schedule->isActive($this->ts('Jan 11 2026 12:00:00')));
        $this->assertFalse($schedule->isActive($this->ts('Jan 12 2026 00:00:00')));
    }

    /** @test */
    public function it_holds_the_wall_clock_across_a_dst_transition()
    {
        $schedule = RecurringSchedule::weekdays('08:00', '17:00', self::TZ);

        // DST starts Sunday Mar 8 2026. On the Monday after, 08:00 local is still
        // the boundary -- an absolute-seconds implementation would drift an hour.
        $this->assertFalse($schedule->isActive($this->ts('Mar 9 2026 07:59:59')));
        $this->assertTrue($schedule->isActive($this->ts('Mar 9 2026 08:00:00')));
        $this->assertTrue($schedule->isActive($this->ts('Mar 9 2026 16:59:59')));
        $this->assertFalse($schedule->isActive($this->ts('Mar 9 2026 17:00:00')));
    }

    /** @test */
    public function an_effective_window_bounds_the_recurrence()
    {
        $schedule = new RecurringSchedule(
            [1, 2, 3, 4, 5],
            '08:00',
            '17:00',
            self::TZ,
            $this->ts('Jan 6 2026 00:00:00'),
            $this->ts('Jan 8 2026 00:00:00')
        );

        $this->assertFalse($schedule->isActive($this->ts('Jan 5 2026 12:00:00')));
        $this->assertTrue($schedule->isActive($this->ts('Jan 6 2026 12:00:00')));
        $this->assertTrue($schedule->isActive($this->ts('Jan 7 2026 12:00:00')));
        $this->assertFalse($schedule->isActive($this->ts('Jan 8 2026 12:00:00')));
    }

    /** @test */
    public function it_reports_its_next_transition()
    {
        $schedule = RecurringSchedule::weekdays('08:00', '17:00', self::TZ);

        $this->assertSame(
            $this->ts('Jan 6 2026 08:00:00'),
            $schedule->nextTransitionAfter($this->ts('Jan 6 2026 06:00:00'))
        );

        $this->assertSame(
            $this->ts('Jan 6 2026 17:00:00'),
            $schedule->nextTransitionAfter($this->ts('Jan 6 2026 12:00:00'))
        );

        // Friday evening -> the next boundary is Monday morning.
        $this->assertSame(
            $this->ts('Jan 12 2026 08:00:00'),
            $schedule->nextTransitionAfter($this->ts('Jan 9 2026 18:00:00'))
        );
    }

    /** @test */
    public function any_of_is_a_union()
    {
        $nightsAndWeekends = new AnyOf([
            RecurringSchedule::weekdays('17:00', '08:00', self::TZ),
            RecurringSchedule::weekends(self::TZ),
        ]);

        $this->assertTrue($nightsAndWeekends->isActive($this->ts('Jan 6 2026 22:00:00')));
        $this->assertTrue($nightsAndWeekends->isActive($this->ts('Jan 10 2026 12:00:00')));
        $this->assertFalse($nightsAndWeekends->isActive($this->ts('Jan 6 2026 12:00:00')));
    }

    /** @test */
    public function all_of_and_not_compose_into_an_exclusion()
    {
        $independenceDay = new DateRangeSchedule(
            $this->ts('Jul 4 2026 00:00:00'),
            $this->ts('Jul 5 2026 00:00:00')
        );

        // Every day, except the 4th of July.
        $schedule = new AllOf([
            RecurringSchedule::everyDay(self::TZ),
            new Not($independenceDay),
        ]);

        $this->assertTrue($schedule->isActive($this->ts('Jul 3 2026 12:00:00')));
        $this->assertFalse($schedule->isActive($this->ts('Jul 4 2026 12:00:00')));
        $this->assertTrue($schedule->isActive($this->ts('Jul 5 2026 12:00:00')));
    }

    /** @test */
    public function always_and_never_do_what_they_say()
    {
        $this->assertTrue((new AlwaysActive())->isActive($this->ts('Jan 6 2026 12:00:00')));
        $this->assertFalse((new NeverActive())->isActive($this->ts('Jan 6 2026 12:00:00')));
    }

    /** @test */
    public function a_date_range_is_half_open()
    {
        $range = new DateRangeSchedule(
            $this->ts('Jan 6 2026 00:00:00'),
            $this->ts('Jan 7 2026 00:00:00')
        );

        $this->assertTrue($range->isActive($this->ts('Jan 6 2026 00:00:00')));
        $this->assertTrue($range->isActive($this->ts('Jan 6 2026 23:59:59')));
        $this->assertFalse($range->isActive($this->ts('Jan 7 2026 00:00:00')));
    }

    /** @test */
    public function the_factory_round_trips_a_composed_schedule()
    {
        $schedule = new AllOf([
            new AnyOf([
                RecurringSchedule::weekdays('17:00', '08:00', self::TZ),
                RecurringSchedule::weekends(self::TZ),
            ]),
            new Not(new DateRangeSchedule(
                $this->ts('Jul 4 2026 00:00:00'),
                $this->ts('Jul 5 2026 00:00:00')
            )),
            new AlwaysActive(),
        ]);

        $restored = ScheduleFactory::fromArray(ScheduleFactory::toArray($schedule));

        $this->assertSame(ScheduleFactory::toArray($schedule), ScheduleFactory::toArray($restored));

        foreach ([
            'Jan 6 2026 22:00:00',
            'Jan 6 2026 12:00:00',
            'Jan 10 2026 12:00:00',
            'Jul 4 2026 22:00:00',
        ] as $when) {
            $this->assertSame(
                $schedule->isActive($this->ts($when)),
                $restored->isActive($this->ts($when)),
                "restored schedule disagrees at $when"
            );
        }
    }

    /** @test */
    public function the_factory_round_trips_through_json()
    {
        $schedule = RecurringSchedule::weekdays('17:00', '08:00', self::TZ);

        $restored = ScheduleFactory::fromJson(\json_encode($schedule->toArray()));

        $this->assertTrue($restored->isActive($this->ts('Jan 6 2026 22:00:00')));
        $this->assertFalse($restored->isActive($this->ts('Jan 6 2026 12:00:00')));
    }

    /** @test */
    public function the_factory_returns_null_for_an_empty_json_column()
    {
        $this->assertNull(ScheduleFactory::fromJson(null));
        $this->assertNull(ScheduleFactory::fromJson(''));
    }

    /** @test */
    public function the_factory_rejects_an_unknown_type()
    {
        $this->expectException(\InvalidArgumentException::class);

        ScheduleFactory::fromArray(['type' => 'nonsense']);
    }

    /** @test */
    public function it_rejects_an_invalid_day_of_week()
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecurringSchedule([0], '08:00', '17:00', self::TZ);
    }

    /** @test */
    public function it_rejects_a_malformed_time_of_day()
    {
        $this->expectException(\InvalidArgumentException::class);

        new RecurringSchedule([1], '8am', '17:00', self::TZ);
    }
}
