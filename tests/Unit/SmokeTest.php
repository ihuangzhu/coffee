<?php

declare(strict_types=1);

test('framework boots', function () {
    expect(app())->not->toBeNull();
    expect(config('app.name'))->toBe('Coffee');
});
