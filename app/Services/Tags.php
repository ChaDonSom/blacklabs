<?php

namespace App\Services;

use Illuminate\Support\Str;

trait Tags {
    public function getTagFromBranch($branch): string {
        $tag = Str::of($branch)->after('release/')->before('-');
        return $this->getTagWithV($tag);
    }

    public function getGitTagFromBranchTag($branchTag): string {
        $hotfixLevelTag = implode('.', array_slice(explode('.', $branchTag), 0, 3)); // v0.15 || v0.15.2
        $tags = $this->runProcess('git tag --list ' . $hotfixLevelTag . '*');
        $tags = explode("\n", $tags);
        $tags = array_filter($tags);
        return end($tags) ?: $branchTag; // sets to the branch tag if there are no tags that match
    }

    public function incrementTag($tag, $part = 'deploy') {
        $tagParts = explode('.', $tag);
        $part = match ($part) {
            'major' => 0,
            'minor' => 1,
            'hotfix' => 2,
            'deploy' => 3,
        };
        if ($part > 2) $tagParts[2] = ($tagParts[2] ?? 0);
        $tagParts[$part] = ($tagParts[$part] ?? 0) + 1;

        // Set all parts after the incremented part to 0 e.g. v0.15.0.4 -> v0.16.0.0
        for ($i = $part + 1; $i < 4; $i++) $tagParts[$i] = 0;

        return implode('.', $tagParts);
    }

    public function getTagWithV(string $tag): string {
        if (Str::startsWith($tag, 'v')) return $tag;
        return 'v' . $tag;
    }

    public function getTagWithoutV(string $tag): string {
        if (Str::startsWith($tag, 'v')) return Str::after($tag, 'v');
        return $tag;
    }
}