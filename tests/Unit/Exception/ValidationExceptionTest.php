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
