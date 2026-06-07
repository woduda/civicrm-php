<?php

declare(strict_types=1);

use Woduda\CiviCRM\Entity\Activity;

it('hydrates an activity from a full API row', function (): void {
    $row = fixtureFirstRow('activity-single.json');
    $activity = Activity::fromArray($row);

    expect($activity->id)->toBe(101)
        ->and($activity->subject)->toBe('Intake call')
        ->and($activity->activityType)->toBe('Phone Call')
        ->and($activity->sourceContactId)->toBe(42)
        ->and($activity->status)->toBe('Completed')
        ->and($activity->rawData)->toBe($row);
});

it('tolerates missing optional fields without throwing', function (): void {
    $activity = Activity::fromArray(['id' => 5]);

    expect($activity->id)->toBe(5)
        ->and($activity->subject)->toBeNull()
        ->and($activity->activityType)->toBeNull()
        ->and($activity->sourceContactId)->toBeNull()
        ->and($activity->status)->toBeNull();
});

it('round-trips mapped fields through toArray', function (): void {
    $activity = Activity::fromArray(fixtureFirstRow('activity-single.json'));
    $exported = $activity->toArray();

    expect($exported)->toMatchArray([
        'id' => 101,
        'subject' => 'Intake call',
        'activity_type_id.name' => 'Phone Call',
        'source_contact_id' => 42,
        'status_id.name' => 'Completed',
    ]);

    $roundTrip = Activity::fromArray($exported);

    expect($roundTrip->id)->toBe($activity->id)
        ->and($roundTrip->subject)->toBe($activity->subject)
        ->and($roundTrip->activityType)->toBe($activity->activityType)
        ->and($roundTrip->sourceContactId)->toBe($activity->sourceContactId)
        ->and($roundTrip->status)->toBe($activity->status);
});
