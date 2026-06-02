<?php

declare(strict_types=1);

use Woduda\CiviCRM\Config;

it('exposes the base url', function (): void {
    $config = new Config('https://example.org/civicrm/ajax/api4/', 'secret');

    expect($config->getBaseUrl())->toBe('https://example.org/civicrm/ajax/api4/');
});

it('exposes the api key', function (): void {
    $config = new Config('https://example.org/civicrm/ajax/api4/', 'secret');

    expect($config->getApiKey())->toBe('secret');
});

it('defaults headers to an empty array', function (): void {
    $config = new Config('https://example.org/civicrm/ajax/api4/', 'secret');

    expect($config->getHeaders())->toBe([]);
});

it('exposes provided headers', function (): void {
    $config = new Config(
        'https://example.org/civicrm/ajax/api4/',
        'secret',
        ['X-Extra' => 'value'],
    );

    expect($config->getHeaders())->toBe(['X-Extra' => 'value']);
});
