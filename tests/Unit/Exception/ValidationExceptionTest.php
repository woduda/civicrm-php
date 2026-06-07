<?php

declare(strict_types=1);

use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\CivicrmException;
use Woduda\CiviCRM\Exception\ValidationException;

it('builds an invalid order direction exception', function (): void {
    $exception = ValidationException::invalidOrderDirection('SIDEWAYS');

    expect($exception)->toBeInstanceOf(CivicrmException::class)
        ->and($exception->getMessage())->toContain('SIDEWAYS')
        ->and($exception->getMessage())->toContain('ASC');
});

it('unifies the library exceptions under CivicrmException', function (): void {
    expect((new ReflectionClass(ValidationException::class))->implementsInterface(CivicrmException::class))->toBeTrue()
        ->and((new ReflectionClass(ApiException::class))->implementsInterface(CivicrmException::class))->toBeTrue();
});

it('builds an unknown custom field exception', function (): void {
    $exception = ValidationException::unknownCustomField('Group', 'field_a');

    expect($exception->getMessage())->toBe('Custom field "Group.field_a" does not exist.');
});

it('builds an unknown country exception', function (): void {
    $exception = ValidationException::unknownCountry('XX');

    expect($exception->getMessage())->toBe('Country "XX" does not exist.');
});

it('builds an unknown state/province exception', function (): void {
    $exception = ValidationException::unknownStateProvince('Unknown');

    expect($exception->getMessage())->toBe('State/province "Unknown" does not exist.');
});

it('builds an empty API result exception', function (): void {
    $exception = ValidationException::emptyApiResult('Email', 'create');

    expect($exception->getMessage())->toBe('Email.create returned no records.');
});
