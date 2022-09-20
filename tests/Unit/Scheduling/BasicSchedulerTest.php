<?php

namespace Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;
use SeanKndy\AlertManager\Scheduling\BasicSchedule;

class BasicSchedulerTest extends TestCase
{
    /** @test */
    public function it_is_active_when_time_given_falls_between_start_and_end_time()
    {
        $startDate = new \DateTime("Aug 21 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Aug 21 1987 11:00:00", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');

        $this->assertTrue($schedule->isActive($startDate->getTimestamp()));
        $this->assertTrue($schedule->isActive($endDate->getTimestamp()));
        $this->assertTrue($schedule->isActive(
            (new \DateTime("Aug 21 1987 09:30:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_is_not_active_when_time_given_falls_outside_start_and_end_time()
    {
        $startDate = new \DateTime("Aug 21 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Aug 21 1987 11:00:00", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');

        $this->assertFalse($schedule->isActive(
            (new \DateTime("Aug 21 1987 7:59:59", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
        $this->assertFalse($schedule->isActive(
            (new \DateTime("Aug 21 1987 11:00:01", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_is_active_when_time_given_falls_between_start_and_end_time_with_future_daily_interval()
    {
        $startDate = new \DateTime("Aug 21 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Aug 21 1987 11:00:00", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_DAILY);
        $schedule->setRepeatInterval(1);

        $this->assertTrue($schedule->isActive(
            (new \DateTime("Aug 22 1987 10:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));

        $this->assertTrue($schedule->isActive(
            (new \DateTime("Aug 21 2020 10:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_is_not_active_when_time_given_falls_outside_start_and_end_time_with_future_daily_interval()
    {
        $startDate = new \DateTime("Aug 21 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Aug 21 1987 11:00:00", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_DAILY);
        $schedule->setRepeatInterval(2);

        $this->assertFalse($schedule->isActive(
            (new \DateTime("Aug 22 1987 10:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_is_active_when_time_given_falls_between_start_and_end_time_with_future_weekly_interval()
    {
        $startDate = new \DateTime("Aug 21 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Aug 21 1987 11:00:00", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_WEEKLY);
        $schedule->setRepeatInterval(4);

        $this->assertTrue($schedule->isActive(
            (new \DateTime("Sep 18 1987 10:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
        $this->assertTrue($schedule->isActive(
            (new \DateTime("Oct 16 1987 10:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_is_active_when_time_given_falls_between_start_and_end_time_with_a_truly_future_weekly_interval()
    {
        // test into the real future relative to the time this test runs

        $startDate = new \DateTime("Aug 21 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Aug 21 1987 11:00:00", new \DateTimeZone("America/Denver"));
        // Aug 21 1987 is a friday and we're repeating every week
        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_WEEKLY);
        $schedule->setRepeatInterval(1);

        $this->assertTrue($schedule->isActive(
            (new \DateTime("next friday", new \DateTimeZone("America/Denver")))
                ->setTime(8, 0, 0)
                ->getTimestamp()
        ));
    }

    /** @test */
    public function it_is_not_active_when_time_given_falls_outside_start_and_end_time_with_future_weekly_interval()
    {
        $startDate = new \DateTime("Aug 21 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Aug 21 1987 11:00:00", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_WEEKLY);
        $schedule->setRepeatInterval(4);

        $this->assertFalse($schedule->isActive(
            (new \DateTime("Sep 11 1987 10:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
        $this->assertFalse($schedule->isActive(
            (new \DateTime("Oct 9 1987 10:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_properly_accounts_for_dst_end_change()
    {
        $startDate = new \DateTime("Oct 4 1987 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Oct 4 1987 08:59:59", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_DAILY);
        $schedule->setRepeatInterval(1);

        // dst ends on Sunday, October 25, 2:00 am
        // so America/Denver is now -6 instead of -7

        $this->assertTrue($schedule->isActive(
            (new \DateTime("Nov 1 1987 08:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_properly_accounts_for_dst_start_change()
    {
        $startDate = new \DateTime("Mar 13 2021 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Mar 13 2021 08:59:59", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_DAILY);
        $schedule->setRepeatInterval(1);

        // dst starts on Sunday, March 14, 2:00 am
        // so America/Denver is now -7 instead of -6

        $this->assertTrue($schedule->isActive(
            (new \DateTime("Mar 15 08:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_properly_accounts_for_leap_year()
    {
        $startDate = new \DateTime("Feb 22 2020 08:00:00", new \DateTimeZone("America/Denver"));
        $endDate = new \DateTime("Feb 22 2020 08:59:59", new \DateTimeZone("America/Denver"));

        $schedule = new BasicSchedule($startDate->getTimestamp(), $endDate->getTimestamp(), 'America/Denver');
        $schedule->setRepeatFrequency(BasicSchedule::FREQ_WEEKLY);
        $schedule->setRepeatInterval(1);

        $this->assertFalse($schedule->isActive(
            (new \DateTime("Feb 28 2020 08:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
        $this->assertTrue($schedule->isActive(
            (new \DateTime("Feb 29 2020 08:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
        $this->assertFalse($schedule->isActive(
            (new \DateTime("Mar 1 2020 08:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
        $this->assertTrue($schedule->isActive(
            (new \DateTime("Mar 2 2024 08:00:00", new \DateTimeZone("America/Denver")))->getTimestamp()
        ));
    }

    /** @test */
    public function it_doesnt_allow_start_time_less_than_end_time()
    {
        $this->expectException(\InvalidArgumentException::class);
        new BasicSchedule(\time(), \time()-1, 'America/Denver');
    }

    /** @test */
    public function it_doesnt_allow_start_time_equal_to_end_time()
    {
        $time = \time();

        $this->expectException(\InvalidArgumentException::class);
        new BasicSchedule($time, $time, 'America/Denver');
    }

    /** @test */
    public function it_doesnt_allow_invalid_repeat_interval()
    {
        $schedule = new BasicSchedule(\time(), \time()+1, 'America/Denver');

        $this->expectException(\InvalidArgumentException::class);
        $schedule->setRepeatInterval(-1);
    }

    /** @test */
    public function it_doesnt_allow_invalid_repeat_frequency()
    {
        $schedule = new BasicSchedule(\time(), \time()+1, 'America/Denver');

        $this->expectException(\InvalidArgumentException::class);
        $schedule->setRepeatFrequency(1234);
    }
}