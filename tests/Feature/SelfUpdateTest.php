<?php

use App\PackagedCommands\SelfUpdate;

function windowsSelfUpdateCommand(): SelfUpdate
{
    return new class extends SelfUpdate
    {
        public function resolveExecutablePathForTest(string $argvZero): string
        {
            $originalArgv = $_SERVER['argv'] ?? null;
            $_SERVER['argv'] = [$argvZero];

            try {
                return $this->resolveCurrentExecutablePath();
            } finally {
                if ($originalArgv === null) {
                    unset($_SERVER['argv']);
                } else {
                    $_SERVER['argv'] = $originalArgv;
                }
            }
        }

        protected function isWindows(): bool
        {
            return true;
        }
    };
}

it('treats windows relative paths with forward slashes as executable paths', function () {
    expect(windowsSelfUpdateCommand()->resolveExecutablePathForTest('./blacklabs.exe'))
        ->toBe('./blacklabs.exe');
});

it('treats windows absolute paths with forward slashes as executable paths', function () {
    expect(windowsSelfUpdateCommand()->resolveExecutablePathForTest('C:/bin/blacklabs.exe'))
        ->toBe('C:/bin/blacklabs.exe');
});
