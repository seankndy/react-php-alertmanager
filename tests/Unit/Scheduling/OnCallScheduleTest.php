<?php

namespace Tests\Unit\Scheduling;

use PHPUnit\Framework\TestCase;
use SeanKndy\AlertManager\Scheduling\AllOf;
use SeanKndy\AlertManager\Scheduling\AnyOf;
use SeanKndy\AlertManager\Scheduling\DateRangeSchedule;
use SeanKndy\AlertManager\Scheduling\Not;
use SeanKndy\AlertManager\Scheduling\RecurringSchedule;
use SeanKndy\AlertManager\Scheduling\OnCall\Layer;
use SeanKndy\AlertManager\Scheduling\OnCall\OnCallSchedule;
use SeanKndy\AlertManager\Scheduling\OnCall\Override;
use SeanKndy\AlertManager\Scheduling\OnCall\ParticipantSchedule;
use SeanKndy\AlertManager\Scheduling\OnCall\Rotation;
use SeanKndy\AlertManager\Scheduling\OnCall\ShiftLength;

class OnCallScheduleTest extends TestCase
{
    private const TZ = 'America/Denver';

    private function ts(string $when): int
    {
        return (new \DateTime($when, new \DateTimeZone(self::TZ)))->getTimestamp();
    }

    private function anchor(): int
    {
        return $this->ts('Jan 5 2026 08:00:00'); // a Monday
    }

    private function weeklyRotation(array $participants): Rotation
    {
        return new Rotation($participants, ShiftLength::weeks(1), $this->anchor(), self::TZ);
    }

    private function scheduleWithRotation(array $participants): OnCallSchedule
    {
        return (new OnCallSchedule('Field Techs'))
            ->addLayer(new Layer('rotation', $this->weeklyRotation($participants)));
    }

    /** @test */
    public function it_resolves_who_is_on_call_from_a_single_rotation()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob', 'carl']);

        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 7 2026 12:00:00')));
        $this->assertSame(['bob'], $schedule->participantsAt($this->ts('Jan 14 2026 12:00:00')));
    }

    /** @test */
    public function it_answers_whether_a_given_participant_is_on_call()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        $this->assertTrue($schedule->isOnCall('alice', $this->ts('Jan 7 2026 12:00:00')));
        $this->assertFalse($schedule->isOnCall('bob', $this->ts('Jan 7 2026 12:00:00')));
    }

    /** @test */
    public function an_override_swaps_one_person_for_another_without_touching_the_rotation()
    {
        // The headline case. Alice is out the week of Jan 12; Bob covers.
        $schedule = $this->scheduleWithRotation(['alice', 'bob', 'carl']);

        $schedule->addOverride(new Override(
            'bob',
            'alice',
            $this->ts('Jan 26 2026 08:00:00'),
            $this->ts('Feb 2 2026 08:00:00'),
            'PTO'
        ));

        // Alice's turn (Jan 26 -> Feb 2) is now Bob's...
        $this->assertSame(['bob'], $schedule->participantsAt($this->ts('Jan 28 2026 12:00:00')));
        $this->assertFalse($schedule->isOnCall('alice', $this->ts('Jan 28 2026 12:00:00')));

        // ...and every other turn is exactly as it was.
        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 7 2026 12:00:00')));
        $this->assertSame(['bob'], $schedule->participantsAt($this->ts('Jan 14 2026 12:00:00')));
        $this->assertSame(['carl'], $schedule->participantsAt($this->ts('Jan 21 2026 12:00:00')));
        $this->assertSame(['bob'], $schedule->participantsAt($this->ts('Feb 2 2026 12:00:00')));
        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Feb 16 2026 12:00:00')));
    }

    /** @test */
    public function an_override_can_cover_part_of_a_shift()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        // Alice is on Jan 5 -> Jan 12. Bob covers only Wed-Thu.
        $schedule->addOverride(new Override(
            'bob',
            'alice',
            $this->ts('Jan 7 2026 00:00:00'),
            $this->ts('Jan 9 2026 00:00:00')
        ));

        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 6 2026 12:00:00')));
        $this->assertSame(['bob'], $schedule->participantsAt($this->ts('Jan 7 2026 12:00:00')));
        $this->assertSame(['bob'], $schedule->participantsAt($this->ts('Jan 8 2026 12:00:00')));
        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 9 2026 12:00:00')));
    }

    /** @test */
    public function an_override_does_nothing_when_the_person_it_replaces_is_not_on_call()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        // Covers alice, but during a window where bob is the one on call.
        $schedule->addOverride(new Override(
            'carl',
            'alice',
            $this->ts('Jan 12 2026 08:00:00'),
            $this->ts('Jan 19 2026 08:00:00')
        ));

        $this->assertSame(['bob'], $schedule->participantsAt($this->ts('Jan 14 2026 12:00:00')));
    }

    /** @test */
    public function a_covering_participant_need_not_be_in_the_rotation()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        $schedule->addOverride(new Override(
            'contractor',
            'alice',
            $this->ts('Jan 6 2026 00:00:00'),
            $this->ts('Jan 8 2026 00:00:00')
        ));

        $this->assertSame(['contractor'], $schedule->participantsAt($this->ts('Jan 7 2026 12:00:00')));
        $this->assertContains('contractor', $schedule->getAllParticipants());
    }

    /** @test */
    public function the_highest_priority_applicable_layer_wins()
    {
        $schedule = (new OnCallSchedule('Network Techs'))
            ->addLayer(new Layer('fallback', Rotation::fixed(['manager'], self::TZ), null, 0))
            ->addLayer(new Layer(
                'after hours',
                $this->weeklyRotation(['alice', 'bob']),
                new AnyOf([
                    RecurringSchedule::weekdays('17:00', '08:00', self::TZ),
                    RecurringSchedule::weekends(self::TZ),
                ]),
                20
            ));

        // Tuesday mid-morning: after-hours layer does not apply, fall back.
        $this->assertSame(['manager'], $schedule->participantsAt($this->ts('Jan 6 2026 10:00:00')));

        // Tuesday evening: after-hours layer applies and masks the fallback.
        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 6 2026 22:00:00')));

        // Saturday: weekend, after-hours layer applies all day.
        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 10 2026 12:00:00')));
    }

    /** @test */
    public function a_restriction_can_exclude_holidays()
    {
        $newYears = new DateRangeSchedule(
            $this->ts('Jan 1 2026 00:00:00'),
            $this->ts('Jan 2 2026 00:00:00')
        );

        $schedule = (new OnCallSchedule('Field Techs'))
            ->addLayer(new Layer('holiday cover', Rotation::fixed(['holiday-tech'], self::TZ), $newYears, 30))
            ->addLayer(new Layer('normal', $this->weeklyRotation(['alice', 'bob']), null, 10));

        $this->assertSame(['holiday-tech'], $schedule->participantsAt($this->ts('Jan 1 2026 12:00:00')));
        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 5 2026 12:00:00')));
    }

    /** @test */
    public function a_layer_can_be_bounded_by_an_effective_window()
    {
        $schedule = (new OnCallSchedule('Field Techs'))
            ->addLayer(new Layer('old crew', $this->weeklyRotation(['alice']), null, 10, null, $this->ts('Feb 1 2026 00:00:00')))
            ->addLayer(new Layer('new crew', $this->weeklyRotation(['zoe']), null, 10, $this->ts('Feb 1 2026 00:00:00')));

        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 15 2026 12:00:00')));
        $this->assertSame(['zoe'], $schedule->participantsAt($this->ts('Feb 15 2026 12:00:00')));
    }

    /** @test */
    public function nobody_is_on_call_when_no_layer_applies()
    {
        $schedule = (new OnCallSchedule('Field Techs'))
            ->addLayer(new Layer(
                'weekends only',
                $this->weeklyRotation(['alice']),
                RecurringSchedule::weekends(self::TZ),
                10
            ));

        $this->assertSame([], $schedule->participantsAt($this->ts('Jan 7 2026 12:00:00')));
        $this->assertNull($schedule->shiftAt($this->ts('Jan 7 2026 12:00:00')));
        $this->assertSame(['alice'], $schedule->participantsAt($this->ts('Jan 10 2026 12:00:00')));
    }

    /** @test */
    public function it_reports_the_next_change_in_who_is_on_call()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        $this->assertSame(
            $this->ts('Jan 12 2026 08:00:00'),
            $schedule->nextChangeAfter($this->ts('Jan 7 2026 12:00:00'))
        );
    }

    /** @test */
    public function the_next_change_accounts_for_an_override_starting()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        $schedule->addOverride(new Override(
            'bob',
            'alice',
            $this->ts('Jan 8 2026 00:00:00'),
            $this->ts('Jan 9 2026 00:00:00')
        ));

        $this->assertSame(
            $this->ts('Jan 8 2026 00:00:00'),
            $schedule->nextChangeAfter($this->ts('Jan 7 2026 12:00:00'))
        );
    }

    /** @test */
    public function a_handoff_between_two_people_who_are_both_still_on_call_is_not_a_change()
    {
        // Overlapping turns: bob is on call in both, so the boundary between them
        // is not a change in who is reachable.
        $schedule = (new OnCallSchedule('Field Techs'))
            ->addLayer(new Layer(
                'pairs',
                new Rotation([['alice', 'bob'], ['alice', 'bob']], ShiftLength::weeks(1), $this->anchor(), self::TZ)
            ));

        $this->assertNull($schedule->nextChangeAfter(
            $this->ts('Jan 7 2026 12:00:00'),
            $this->ts('Feb 1 2026 00:00:00')
        ));
    }

    /** @test */
    public function it_builds_a_timeline_of_contiguous_blocks()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob', 'carl']);

        $timeline = $schedule->timeline(
            $this->ts('Jan 5 2026 08:00:00'),
            $this->ts('Jan 26 2026 08:00:00')
        );

        $this->assertCount(3, $timeline);
        $this->assertSame(['alice'], $timeline[0]->getParticipants());
        $this->assertSame($this->ts('Jan 12 2026 08:00:00'), $timeline[0]->getEndTime());
        $this->assertSame(['bob'], $timeline[1]->getParticipants());
        $this->assertSame(['carl'], $timeline[2]->getParticipants());
    }

    /** @test */
    public function the_timeline_shows_an_override_as_its_own_block()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        $schedule->addOverride(new Override(
            'carl',
            'alice',
            $this->ts('Jan 7 2026 00:00:00'),
            $this->ts('Jan 9 2026 00:00:00')
        ));

        $timeline = $schedule->timeline(
            $this->ts('Jan 5 2026 08:00:00'),
            $this->ts('Jan 12 2026 08:00:00')
        );

        $this->assertCount(3, $timeline);
        $this->assertSame(['alice'], $timeline[0]->getParticipants());
        $this->assertSame(['carl'], $timeline[1]->getParticipants());
        $this->assertSame($this->ts('Jan 7 2026 00:00:00'), $timeline[1]->getStartTime());
        $this->assertSame($this->ts('Jan 9 2026 00:00:00'), $timeline[1]->getEndTime());
        $this->assertSame(['alice'], $timeline[2]->getParticipants());
    }

    /** @test */
    public function it_collects_every_participant_including_those_only_named_in_overrides()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        $schedule->addOverride(new Override(
            'contractor',
            'alice',
            $this->ts('Jan 6 2026 00:00:00'),
            $this->ts('Jan 8 2026 00:00:00')
        ));

        $participants = $schedule->getAllParticipants();
        \sort($participants);

        $this->assertSame(['alice', 'bob', 'contractor'], $participants);
    }

    /** @test */
    public function the_participant_schedule_adapter_answers_for_one_person()
    {
        $schedule = $this->scheduleWithRotation(['alice', 'bob']);

        $alice = new ParticipantSchedule($schedule, 'alice');
        $bob = new ParticipantSchedule($schedule, 'bob');

        $this->assertTrue($alice->isActive($this->ts('Jan 7 2026 12:00:00')));
        $this->assertFalse($bob->isActive($this->ts('Jan 7 2026 12:00:00')));

        $this->assertFalse($alice->isActive($this->ts('Jan 14 2026 12:00:00')));
        $this->assertTrue($bob->isActive($this->ts('Jan 14 2026 12:00:00')));
    }

    /** @test */
    public function the_participant_schedule_accepts_integer_ids()
    {
        // cmalertd keys participants by integer user id.
        $schedule = (new OnCallSchedule('Field Techs'))
            ->addLayer(new Layer('rotation', new Rotation([12, 34], ShiftLength::weeks(1), $this->anchor(), self::TZ)));

        $this->assertTrue((new ParticipantSchedule($schedule, 12))->isActive($this->ts('Jan 7 2026 12:00:00')));
        $this->assertFalse((new ParticipantSchedule($schedule, 34))->isActive($this->ts('Jan 7 2026 12:00:00')));
    }

    /** @test */
    public function it_round_trips_a_whole_schedule_through_an_array()
    {
        $schedule = (new OnCallSchedule('Field Techs'))
            ->addLayer(new Layer('fallback', Rotation::fixed(['manager'], self::TZ), null, 0))
            ->addLayer(new Layer(
                'after hours',
                $this->weeklyRotation(['alice', 'bob']),
                new AllOf([
                    new AnyOf([
                        RecurringSchedule::weekdays('17:00', '08:00', self::TZ),
                        RecurringSchedule::weekends(self::TZ),
                    ]),
                    new Not(new DateRangeSchedule(
                        $this->ts('Jul 4 2026 00:00:00'),
                        $this->ts('Jul 5 2026 00:00:00')
                    )),
                ]),
                20
            ))
            ->addOverride(new Override('carl', 'alice', $this->ts('Feb 1 2026 00:00:00'), $this->ts('Feb 8 2026 00:00:00'), 'PTO'));

        $restored = OnCallSchedule::fromArray($schedule->toArray());

        $this->assertSame($schedule->toArray(), $restored->toArray());

        foreach ([
            'Jan 6 2026 10:00:00',
            'Jan 6 2026 22:00:00',
            'Feb 3 2026 22:00:00',
            'Jul 4 2026 22:00:00',
        ] as $when) {
            $this->assertSame(
                $schedule->participantsAt($this->ts($when)),
                $restored->participantsAt($this->ts($when)),
                "restored schedule disagrees at $when"
            );
        }
    }

    /** @test */
    public function an_override_must_end_after_it_starts()
    {
        $this->expectException(\InvalidArgumentException::class);

        new Override('bob', 'alice', 1000, 1000);
    }
}
