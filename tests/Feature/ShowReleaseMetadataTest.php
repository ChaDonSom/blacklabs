<?php

it('shows all release metadata when no branch specified', function () {
    // Create temporary directory with metadata
    $tmpDir = sys_get_temp_dir() . '/blacklabs_test_' . uniqid();
    mkdir($tmpDir);
    $origDir = getcwd();
    chdir($tmpDir);

    // Create metadata file
    $meta = [
        'release/v1.2.3-0' => [
            'issues' => ['101', '102'],
            'version' => 'v1.2.3-0',
            'created_at' => '2025-01-22T10:00:00+00:00',
            'updated_at' => '2025-01-22T10:00:00+00:00',
            'created_by' => 'tester <test@example.com>'
        ]
    ];

    $path = getcwd() . '/.blacklabs/releases.json';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $this->artisan('show-release-metadata')
        ->assertExitCode(0)
        ->expectsOutputToContain('release/v1.2.3-0');

    // Cleanup
    chdir($origDir);
    unlink($path);
    rmdir(dirname($path));
    rmdir($tmpDir);
});

it('shows specific branch metadata when branch specified', function () {
    // Create temporary directory with metadata
    $tmpDir = sys_get_temp_dir() . '/blacklabs_test_' . uniqid();
    mkdir($tmpDir);
    $origDir = getcwd();
    chdir($tmpDir);

    // Create metadata file
    $meta = [
        'release/v1.2.3-0' => [
            'issues' => ['101', '102'],
            'version' => 'v1.2.3-0',
            'created_at' => '2025-01-22T10:00:00+00:00',
            'updated_at' => '2025-01-22T10:00:00+00:00',
            'created_by' => 'tester <test@example.com>'
        ]
    ];

    $path = getcwd() . '/.blacklabs/releases.json';
    if (!is_dir(dirname($path))) {
        mkdir(dirname($path), 0755, true);
    }
    file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

    $this->artisan('show-release-metadata release/v1.2.3-0')
        ->assertExitCode(0)
        ->expectsOutputToContain('Version: v1.2.3-0')
        ->expectsOutputToContain('Issues: 101, 102');

    // Cleanup
    chdir($origDir);
    unlink($path);
    rmdir(dirname($path));
    rmdir($tmpDir);
});

it('handles missing metadata gracefully', function () {
    $tmpDir = sys_get_temp_dir() . '/blacklabs_test_' . uniqid();
    mkdir($tmpDir);
    $origDir = getcwd();
    chdir($tmpDir);

    $this->artisan('show-release-metadata')
        ->assertExitCode(0)
        ->expectsOutputToContain('No release metadata found');

    // Cleanup
    chdir($origDir);
    // Remove .blacklabs directory if created
    $blacklabsDir = $tmpDir . '/.blacklabs';
    if (is_dir($blacklabsDir)) {
        if (file_exists($blacklabsDir . '/releases.json')) {
            unlink($blacklabsDir . '/releases.json');
        }
        rmdir($blacklabsDir);
    }
    rmdir($tmpDir);
});
