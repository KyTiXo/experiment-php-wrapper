<?php

declare(strict_types=1);

use JitRouter\Http\RequestParser;

global $runner;

$runner->add('parse service route', static function (): void {
    $r = RequestParser::parseServiceRoute('/api/services/my-api/v1/users');
    assertSame('my-api', $r['service']);
    assertSame('v1/users', $r['subpath']);
});

$runner->add('invalid service route returns null', static function (): void {
    assertSame(null, RequestParser::parseServiceRoute('/api/other'));
});
