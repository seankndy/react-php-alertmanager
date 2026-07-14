<?php

namespace Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;
use SeanKndy\AlertManager\Scheduling\OnCall\Rotation;
use SeanKndy\AlertManager\Scheduling\OnCall\ShiftLength;

class RotationTest extends TestCase
{
    private const TZ = 'America/Denver';

    private function ts(string $when): int
    {
        return (new \DateTime($when, new \DateTimeZone(self::TZ)))->getTimestamp();
    }

    private function anchor(): int
    {
        // a Monday
        return $this->ts('Jan 5 2026 08:00:00');
    }

    /** @test */
    public function it_puts_the_first_participant_on_call_at_the_anchor()
    {
        $rotation = new Rotation(['alice', 'bob', 'carl'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $this->assertSame(['alice'], $rotation->participantsAt($this->anchor()));
    }

    /** @test */
    public function it_rotates_through_participants_in_order()
    {
        $rotation = new Rotation(['alice', 'bob', 'carl'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $this->assertSame(['alice'], $rotation->participantsAt($this->ts('Jan 8 2026 12:00:00')));
        $this->assertSame(['bob'], $rotation->participantsAt($this->ts('Jan 12 2026 08:00:00')));
        $this->assertSame(['carl'], $rotation->participantsAt($this->ts('Jan 19 2026 08:00:00')));
        $this->assertSame(['alice'], $rotation->participantsAt($this->ts('Jan 26 2026 08:00:00')));
    }

    /** @test */
    public function it_hands_off_exactly_at_the_boundary_and_not_a_second_before()
    {
        $rotation = new Rotation(['alice', 'bob'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $this->assertSame(['alice'], $rotation->participantsAt($this->ts('Jan 12 2026 07:59:59')));
        $this->assertSame(['bob'], $rotation->participantsAt($this->ts('Jan 12 2026 08:00:00')));
    }

    /** @test */
    public function it_resolves_times_before_the_anchor()
    {
        $rotation = new Rotation(['alice', 'bob', 'carl'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        // the week before the anchor belongs to the participant before alice
        $this->assertSame(['carl'], $rotation->participantsAt($this->ts('Dec 29 2025 08:00:00')));
        $this->assertSame(['bob'], $rotation->participantsAt($this->ts('Dec 22 2025 08:00:00')));
    }

    /** @test */
    public function it_supports_multiple_participants_per_turn()
    {
        $rotation = new Rotation(
            [['alice', 'bob'], ['carl', 'dave']],
            ShiftLength::weeks(1),
            $this->anchor(),
            self::TZ
        );

        $this->assertSame(['alice', 'bob'], $rotation->participantsAt($this->ts('Jan 7 2026 12:00:00')));
        $this->assertSame(['carl', 'dave'], $rotation->participantsAt($this->ts('Jan 14 2026 12:00:00')));
    }

    /** @test */
    public function it_supports_twelve_hour_day_night_shifts()
    {
        $rotation = new Rotation(
            ['day-tech', 'night-tech'],
            ShiftLength::hours(12),
            $this->ts('Jan 5 2026 08:00:00'),
            self::TZ
        );

        $this->assertSame(['day-tech'], $rotation->participantsAt($this->ts('Jan 5 2026 08:00:00')));
        $this->assertSame(['day-tech'], $rotation->participantsAt($this->ts('Jan 5 2026 19:59:59')));
        $this->assertSame(['night-tech'], $rotation->participantsAt($this->ts('Jan 5 2026 20:00:00')));
        $this->assertSame(['night-tech'], $rotation->participantsAt($this->ts('Jan 6 2026 07:59:59')));
        $this->assertSame(['day-tech'], $rotation->participantsAt($this->ts('Jan 6 2026 08:00:00')));
    }

    /** @test */
    public function it_supports_daily_shifts()
    {
        $rotation = new Rotation(['a', 'b', 'c'], ShiftLength::days(1), $this->anchor(), self::TZ);

        $this->assertSame(['a'], $rotation->participantsAt($this->ts('Jan 5 2026 09:00:00')));
        $this->assertSame(['b'], $rotation->participantsAt($this->ts('Jan 6 2026 09:00:00')));
        $this->assertSame(['c'], $rotation->participantsAt($this->ts('Jan 7 2026 09:00:00')));
        $this->assertSame(['a'], $rotation->participantsAt($this->ts('Jan 8 2026 09:00:00')));
    }

    /** @test */
    public function it_keeps_the_handoff_on_the_wall_clock_across_the_spring_dst_transition()
    {
        // DST starts Sunday Mar 8 2026 at 02:00 in America/Denver.
        $rotation = new Rotation(['alice', 'bob'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        // Mar 9 is a Monday on the far side of the transition. Handoff must still
        // be at 08:00 local, not 07:00 or 09:00.
        $handoff = $this->ts('Mar 9 2026 08:00:00');

        $this->assertSame(
            '08:00',
            (new \DateTime('@' . $handoff))
                ->setTimezone(new \DateTimeZone(self::TZ))
                ->format('H:i')
        );

        $this->assertNotSame(
            $rotation->participantsAt($handoff - 1),
            $rotation->participantsAt($handoff),
            'a handoff should occur exactly at 08:00 local on the Monday after DST starts'
        );

        // and the shift boundary the rotation reports agrees with the wall clock
        $shift = $rotation->shiftAt($this->ts('Mar 9 2026 12:00:00'));
        $this->assertSame($handoff, $shift->getStartTime());
    }

    /** @test */
    public function it_keeps_the_handoff_on_the_wall_clock_across_the_autumn_dst_transition()
    {
        // DST ends Sunday Nov 1 2026 at 02:00 in America/Denver.
        $rotation = new Rotation(['alice', 'bob'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $handoff = $this->ts('Nov 2 2026 08:00:00');

        $shift = $rotation->shiftAt($this->ts('Nov 2 2026 12:00:00'));

        $this->assertSame($handoff, $shift->getStartTime());
        $this->assertSame(
            '08:00',
            (new \DateTime('@' . $shift->getStartTime()))
                ->setTimezone(new \DateTimeZone(self::TZ))
                ->format('H:i')
        );
    }

    /** @test */
    public function it_keeps_twelve_hour_handoffs_on_the_wall_clock_across_dst()
    {
        $rotation = new Rotation(
            ['day', 'night'],
            ShiftLength::hours(12),
            $this->ts('Jan 5 2026 08:00:00'),
            self::TZ
        );

        // The day after DST starts, handoffs must still land on 08:00 and 20:00.
        $shift = $rotation->shiftAt($this->ts('Mar 9 2026 10:00:00'));

        $this->assertSame(
            '08:00',
            (new \DateTime('@' . $shift->getStartTime()))
                ->setTimezone(new \DateTimeZone(self::TZ))
                ->format('H:i')
        );
        $this->assertSame(
            '20:00',
            (new \DateTime('@' . $shift->getEndTime()))
                ->setTimezone(new \DateTimeZone(self::TZ))
                ->format('H:i')
        );
    }

    /** @test */
    public function it_stays_correct_far_into_the_future()
    {
        // The whole point of the modular lookup: no walking, no drift.
        $rotation = new Rotation(['alice', 'bob', 'carl'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        // Jan 5 2026 + 300 weeks = Oct 5 2031, a Monday. 300 % 3 == 0 -> alice.
        $this->assertSame(['alice'], $rotation->participantsAt($this->ts('Oct 6 2031 08:00:00')));
    }

    /** @test */
    public function it_reports_the_shift_window()
    {
        $rotation = new Rotation(['alice', 'bob'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $shift = $rotation->shiftAt($this->ts('Jan 7 2026 12:00:00'));

        $this->assertSame(['alice'], $shift->getParticipants());
        $this->assertSame($this->ts('Jan 5 2026 08:00:00'), $shift->getStartTime());
        $this->assertSame($this->ts('Jan 12 2026 08:00:00'), $shift->getEndTime());
        $this->assertSame(0, $shift->getIndex());
    }

    /** @test */
    public function it_lists_shifts_between_two_times()
    {
        $rotation = new Rotation(['alice', 'bob', 'carl'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $shifts = $rotation->shiftsBetween(
            $this->ts('Jan 5 2026 08:00:00'),
            $this->ts('Jan 26 2026 08:00:00')
        );

        $this->assertCount(3, $shifts);
        $this->assertSame(['alice'], $shifts[0]->getParticipants());
        $this->assertSame(['bob'], $shifts[1]->getParticipants());
        $this->assertSame(['carl'], $shifts[2]->getParticipants());
    }

    /** @test */
    public function it_reports_the_next_handoff()
    {
        $rotation = new Rotation(['alice', 'bob'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $this->assertSame(
            $this->ts('Jan 12 2026 08:00:00'),
            $rotation->nextHandoffAfter($this->ts('Jan 7 2026 12:00:00'))
        );
    }

    /** @test */
    public function a_fixed_rotation_always_returns_the_same_participants()
    {
        $rotation = Rotation::fixed(['manager'], self::TZ);

        $this->assertSame(['manager'], $rotation->participantsAt($this->ts('Jan 5 2026 08:00:00')));
        $this->assertSame(['manager'], $rotation->participantsAt($this->ts('Jul 4 2030 03:00:00')));
    }

    /** @test */
    public function reordering_participants_does_not_move_any_boundary()
    {
        // This is the property BasicSchedule could not offer: the schedule is the
        // turn order, so changing it is a one-field edit, not a rewrite.
        $before = new Rotation(['alice', 'bob', 'carl'], ShiftLength::weeks(1), $this->anchor(), self::TZ);
        $after = new Rotation(['alice', 'carl', 'bob'], ShiftLength::weeks(1), $this->anchor(), self::TZ);

        $t = $this->ts('Jan 14 2026 12:00:00');

        $this->assertSame(
            $before->shiftAt($t)->getStartTime(),
            $after->shiftAt($t)->getStartTime()
        );
        $this->assertSame(['bob'], $before->participantsAt($t));
        $this->assertSame(['carl'], $after->participantsAt($t));
    }

    /** @test */
    public function it_round_trips_through_an_array()
    {
        $rotation = new Rotation(
            [['alice', 'bob'], ['carl']],
            ShiftLength::hours(12),
            $this->anchor(),
            self::TZ
        );

        $restored = Rotation::fromArray($rotation->toArray());

        $this->assertSame($rotation->toArray(), $restored->toArray());
        $this->assertSame(
            $rotation->participantsAt($this->ts('Jan 6 2026 09:00:00')),
            $restored->participantsAt($this->ts('Jan 6 2026 09:00:00'))
        );
    }

    /** @test */
    public function it_requires_at_least_one_turn()
    {
        $this->expectException(\InvalidArgumentException::class);

        new Rotation([], ShiftLength::weeks(1), $this->anchor(), self::TZ);
    }

    /** @test */
    public function it_rejects_a_zero_length_shift()
    {
        $this->expectException(\InvalidArgumentException::class);

        ShiftLength::weeks(0);
    }
}
