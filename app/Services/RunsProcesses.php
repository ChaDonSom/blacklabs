<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

trait RunsProcesses
{
    public function runProcess(array|string|null $command)
    {
        $result = Process::run($command);
        if (!$result->successful()) {
            if ($result->errorOutput() && $result->output()) {
                throw new \Exception($result->errorOutput() . "\n" . $result->output());
            } else {
                throw new \Exception($result->errorOutput() ?: $result->output());
            }
        }
        return trim($result->output());
    }

    public function getDefaultBranch(string $remote = 'origin'): string
    {
        try {
            $remoteHead = $this->runProcess("git symbolic-ref --quiet --short refs/remotes/{$remote}/HEAD");

            return preg_replace('#^'.preg_quote($remote, '#').'/#', '', $remoteHead, 1) ?? $remoteHead;
        } catch (\Exception) {
            return $this->runProcess('git rev-parse --abbrev-ref HEAD');
        }
    }
}
