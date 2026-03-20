#!/usr/bin/env php
<?php

require __DIR__.'/../vendor/autoload.php';

use App\Services\ReleaseAssets;

$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php scripts/release-asset-name.php <build-output-path>\n");

    exit(1);
}

$assetName = ReleaseAssets::fromBuildOutput($path);

if ($assetName !== null) {
    echo $assetName;
}
