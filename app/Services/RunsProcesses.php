<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

trait RunsProcesses {
    public function runProcess($command) {
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
}