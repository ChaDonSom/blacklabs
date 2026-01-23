<?php

use PHPUnit\Framework\TestCase;
use App\Services\TracksReleaseMetadata;

final class TracksReleaseMetadataEdgeCasesTest extends TestCase
{
    /**
     * Minimal object to expose trait methods without external dependencies.
     */
    private function makeObjectWithTrait()
    {
        return new class {
            use TracksReleaseMetadata;

            // Provide a no-op runProcess so saveMetadataFile won't throw.
            public function runProcess(string $cmd)
            {
                return '';
            }

            public function warn(string $msg)
            {
                // no-op for tests
            }
        };
    }

    public function test_missing_metadata_file_is_initialized_and_returns_empty(): void
    {
        $tmpDir = sys_get_temp_dir() . '/blacklabs_test_' . uniqid();
        mkdir($tmpDir);
        chdir($tmpDir);

        // Ensure no metadata file exists
        $path = getcwd() . '/.blacklabs/releases.json';
        if (file_exists($path)) unlink($path);
        if (is_dir(dirname($path))) rmdir(dirname($path));

        $obj = $this->makeObjectWithTrait();
        $result = $obj->getAllReleaseMetadata();

        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Expected metadata to be empty when no file exists');
        $this->assertFileExists($path, 'Expected initializeMetadataFile to create the metadata file');

        // cleanup
        unlink($path);
        rmdir(dirname($path));
        rmdir($tmpDir);
    }

    public function test_corrupted_metadata_file_returns_empty_array(): void
    {
        $tmpDir = sys_get_temp_dir() . '/blacklabs_test_' . uniqid();
        mkdir($tmpDir);
        chdir($tmpDir);

        $dir = getcwd() . '/.blacklabs';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $path = $dir . '/releases.json';

        // Write corrupted JSON
        file_put_contents($path, "{ this is not valid json \n");

        $obj = $this->makeObjectWithTrait();
        $result = $obj->getAllReleaseMetadata();

        $this->assertIsArray($result);
        $this->assertEmpty($result, 'Expected corrupted JSON to be treated as empty metadata');

        // cleanup
        unlink($path);
        rmdir($dir);
        rmdir($tmpDir);
    }
}
