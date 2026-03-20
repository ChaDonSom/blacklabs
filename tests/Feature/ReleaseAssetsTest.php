<?php

use App\Services\ReleaseAssets;

it('resolves packed binary release assets for supported platforms', function () {
    expect(ReleaseAssets::forPlatform('Linux', 'x86_64'))->toBe('blacklabs-linux-x64')
        ->and(ReleaseAssets::forPlatform('Linux', 'arm64'))->toBe('blacklabs-linux-arm64')
        ->and(ReleaseAssets::forPlatform('Darwin', 'amd64'))->toBe('blacklabs-macos-x64')
        ->and(ReleaseAssets::forPlatform('Darwin', 'aarch64'))->toBe('blacklabs-macos-arm64')
        ->and(ReleaseAssets::forPlatform('Windows', 'x64'))->toBe('blacklabs-windows-x64.exe');
});

it('maps phpacker build output files to canonical release assets', function () {
    expect(ReleaseAssets::fromBuildOutput('/tmp/build/linux/x64/blacklabs'))->toBe('blacklabs-linux-x64')
        ->and(ReleaseAssets::fromBuildOutput('/tmp/build/linux/arm64/blacklabs'))->toBe('blacklabs-linux-arm64')
        ->and(ReleaseAssets::fromBuildOutput('/tmp/build/mac/amd64/blacklabs'))->toBe('blacklabs-macos-x64')
        ->and(ReleaseAssets::fromBuildOutput('/tmp/build/darwin/arm64/blacklabs'))->toBe('blacklabs-macos-arm64')
        ->and(ReleaseAssets::fromBuildOutput('/tmp/build/windows/x64/blacklabs.exe'))->toBe('blacklabs-windows-x64.exe');
});

it('returns null for unknown build output files', function () {
    expect(ReleaseAssets::fromBuildOutput('/tmp/build/freebsd/x64/blacklabs'))->toBeNull();
});

it('throws for unsupported platform combinations', function () {
    expect(fn () => ReleaseAssets::forPlatform('Windows', 'arm64'))
        ->toThrow(Exception::class, 'No Windows release asset is published for arm64.');
});
