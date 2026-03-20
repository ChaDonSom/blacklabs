#!/usr/bin/env php
<?php

use App\Services\ReleaseAssets;

require __DIR__.'/../vendor/autoload.php';

$path = $argv[1] ?? null;

if ($path === null) {
    fwrite(STDERR, "Usage: php scripts/release-asset-name.php <build-output-path>\n");

    exit(1);
}

$assetName = ReleaseAssets::fromBuildOutput($path);

if ($assetName !== null) {
    echo $assetName;
}
