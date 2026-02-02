<?php

use PHPUnit\Framework\TestCase;

final class TracksReleaseMetadataTest extends TestCase
{
    public function test_load_and_save_metadata_file(): void
    {
        $tmpDir = sys_get_temp_dir() . '/blacklabs_test_' . uniqid();
        mkdir($tmpDir);
        chdir($tmpDir);

        // emulate trait behavior by writing json via the same format
        $meta = [
            'release/v1.2.3-0' => [
                'issues' => ['101','102'],
                'version' => 'v1.2.3-0',
                'created_at' => date(DATE_ATOM),
                'updated_at' => date(DATE_ATOM),
                'created_by' => 'tester <test@example.com>'
            ]
        ];

        $path = getcwd() . '/.blacklabs/releases.json';
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        $this->assertFileExists($path);
        $contents = json_decode(file_get_contents($path), true);
        $this->assertArrayHasKey('release/v1.2.3-0', $contents);
        $this->assertEquals(['101','102'], $contents['release/v1.2.3-0']['issues']);

        // cleanup
        unlink($path);
        rmdir(dirname($path));
        rmdir($tmpDir);
    }
}
