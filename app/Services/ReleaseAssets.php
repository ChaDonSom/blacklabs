<?php

namespace App\Services;

use Exception;

class ReleaseAssets
{
    public const PHAR = 'blacklabs';

    public static function currentBinary(): string
    {
        return static::forPlatform(PHP_OS_FAMILY, php_uname('m'));
    }

    public static function forPlatform(string $operatingSystemFamily, string $architecture): string
    {
        $architecture = static::normalizeArchitecture($architecture);

        return match ($operatingSystemFamily) {
            'Linux' => "blacklabs-linux-{$architecture}",
            'Darwin' => "blacklabs-macos-{$architecture}",
            'Windows' => match ($architecture) {
                'x64' => 'blacklabs-windows-x64.exe',
                default => throw new Exception("No Windows release asset is published for {$architecture}."),
            },
            default => throw new Exception('Packed binary self-update is not supported on '.$operatingSystemFamily.'.'),
        };
    }

    public static function fromBuildOutput(string $path): ?string
    {
        $lowerPath = strtolower($path);

        return match (true) {
            str_contains($lowerPath, 'windows') && str_contains($lowerPath, 'x64') && str_ends_with($lowerPath, '.exe') => 'blacklabs-windows-x64.exe',
            (str_contains($lowerPath, 'mac') || str_contains($lowerPath, 'darwin') || str_contains($lowerPath, 'macos'))
                && str_contains($lowerPath, 'arm64') => 'blacklabs-macos-arm64',
            (str_contains($lowerPath, 'mac') || str_contains($lowerPath, 'darwin') || str_contains($lowerPath, 'macos'))
                && (str_contains($lowerPath, 'x64') || str_contains($lowerPath, 'amd64')) => 'blacklabs-macos-x64',
            str_contains($lowerPath, 'linux')
                && (str_contains($lowerPath, 'arm64') || str_contains($lowerPath, 'aarch64')) => 'blacklabs-linux-arm64',
            str_contains($lowerPath, 'linux')
                && (str_contains($lowerPath, 'x64') || str_contains($lowerPath, 'amd64')) => 'blacklabs-linux-x64',
            default => null,
        };
    }

    public static function normalizeArchitecture(string $architecture): string
    {
        return match (strtolower($architecture)) {
            'x86_64', 'amd64', 'x64' => 'x64',
            'aarch64', 'arm64' => 'arm64',
            default => throw new Exception("Unsupported architecture: {$architecture}."),
        };
    }
}
