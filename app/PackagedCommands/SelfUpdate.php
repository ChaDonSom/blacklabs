<?php

namespace App\PackagedCommands;

use App\Services\ReleaseAssets;
use Exception;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Components\Updater\Updater as PharUpdater;
use LaravelZero\Framework\Providers\Build\Build;
use Symfony\Component\Process\ExecutableFinder;

class SelfUpdate extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'self-update';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Self-update the installed application';

    public function handle(Build $build)
    {
        if (PHP_SAPI === 'micro') {
            return $this->updatePackedBinary();
        }

        if (! $build->isRunning() || ! app()->environment('production')) {
            $this->error('Self-update is only available from packaged Blacklabs builds.');

            return 1;
        }

        app(PharUpdater::class)->update($this->output);

        return 0;
    }

    protected function updatePackedBinary()
    {
        $currentPath = $this->resolveCurrentExecutablePath();
        $assetName = ReleaseAssets::currentBinary();
        $release = $this->fetchLatestRelease();
        $latestVersion = $release['tag_name'] ?? null;

        if (! $latestVersion) {
            throw new Exception('Could not determine the latest release version.');
        }

        $currentVersion = $this->normalizeVersion((string) config('app.version'));

        if ($this->normalizeVersion($latestVersion) === $currentVersion) {
            $this->info('You have the latest version installed.');

            return 0;
        }

        $asset = collect($release['assets'] ?? [])->firstWhere('name', $assetName);

        if (! $asset) {
            $this->error("No release asset named {$assetName} was found for {$latestVersion}.");

            return 1;
        }

        $downloadPath = $currentPath.'.download';
        $backupPath = $currentPath.'.old';

        $this->info("Downloading {$assetName}...");

        $response = Http::withHeaders([
            'User-Agent' => 'blacklabs self-update',
        ])->withOptions([
            'sink' => $downloadPath,
        ])->get($asset['browser_download_url']);

        if (! $response->successful()) {
            $this->deleteFileIfExists($downloadPath, 'the incomplete downloaded update');

            throw new Exception("Failed to download {$assetName}.");
        }

        $this->prepareDownloadedBinary($downloadPath, $currentPath);

        if ($this->isWindows()) {
            $scriptPath = $this->writeWindowsSwapScript(
                currentPath: $currentPath,
                downloadPath: $downloadPath,
                backupPath: $backupPath,
            );

            $this->launchWindowsSwapScript($scriptPath);
            $this->info("Downloaded {$latestVersion}. The binary will be replaced after this process exits.");
            $this->info('Run blacklabs again to use the updated binary.');

            return 0;
        }

        $this->swapUnixBinary(
            currentPath: $currentPath,
            downloadPath: $downloadPath,
            backupPath: $backupPath,
        );

        $this->info("Updated from {$currentVersion} to {$latestVersion}.");

        return 0;
    }

    protected function fetchLatestRelease(): array
    {
        $response = Http::acceptJson()->withHeaders([
            'User-Agent' => 'blacklabs self-update',
        ])->get('https://api.github.com/repos/ChaDonSom/blacklabs/releases/latest');

        if (! $response->successful()) {
            throw new Exception('Failed to fetch the latest GitHub release metadata (HTTP '.$response->status().').');
        }

        return $response->json();
    }

    protected function resolveCurrentExecutablePath(): string
    {
        $argvZero = $_SERVER['argv'][0] ?? '';

        if (! $argvZero) {
            throw new Exception('Could not determine the current executable path.');
        }

        if (str_contains($argvZero, DIRECTORY_SEPARATOR) || $this->isWindowsAbsolutePath($argvZero)) {
            return realpath($argvZero) ?: $argvZero;
        }

        $resolvedPath = (new ExecutableFinder())->find($argvZero);

        if ($resolvedPath) {
            return realpath($resolvedPath) ?: $resolvedPath;
        }

        throw new Exception("Could not resolve the current executable path for {$argvZero}.");
    }

    protected function prepareDownloadedBinary(string $downloadPath, string $currentPath): void
    {
        if (! is_file($downloadPath) || filesize($downloadPath) === 0) {
            throw new Exception('Downloaded update file is missing or empty.');
        }

        $currentPermissions = @fileperms($currentPath);
        chmod($downloadPath, $currentPermissions ?: 0755);
    }

    protected function swapUnixBinary(string $currentPath, string $downloadPath, string $backupPath): void
    {
        $this->deleteFileIfExists($backupPath, 'the previous backup');

        if (! rename($currentPath, $backupPath)) {
            throw new Exception("Could not back up {$currentPath} before replacing it.");
        }

        if (! rename($downloadPath, $currentPath)) {
            if (! rename($backupPath, $currentPath)) {
                throw new Exception("Could not move the downloaded update into place for {$currentPath}, and the original binary could not be restored from {$backupPath}.");
            }

            throw new Exception("Could not move the downloaded update into place for {$currentPath}.");
        }
    }

    protected function deleteFileIfExists(string $path, string $description): void
    {
        if (is_file($path) && ! unlink($path)) {
            throw new Exception("Could not remove {$description} at {$path}.");
        }
    }

    protected function writeWindowsSwapScript(string $currentPath, string $downloadPath, string $backupPath): string
    {
        $scriptPath = $currentPath.'.update.cmd';

        $script = implode(PHP_EOL, [
            '@echo off',
            'setlocal',
            'ping 127.0.0.1 -n 3 >NUL',
            'if exist '.$this->quoteForWindows($backupPath).' del /F /Q '.$this->quoteForWindows($backupPath).' >NUL',
            'move /Y '.$this->quoteForWindows($currentPath).' '.$this->quoteForWindows($backupPath).' >NUL || exit /b 1',
            'move /Y '.$this->quoteForWindows($downloadPath).' '.$this->quoteForWindows($currentPath).' >NUL || exit /b 1',
            'del '.$this->quoteForWindows('%~f0'),
            '',
        ]);

        if (file_put_contents($scriptPath, $script) === false) {
            throw new Exception("Could not write the Windows update script at {$scriptPath}.");
        }

        return $scriptPath;
    }

    protected function launchWindowsSwapScript(string $scriptPath): void
    {
        $command = 'start "" /B cmd /C '.$this->quoteForWindows($scriptPath);
        $process = @popen($command, 'r');

        if (! $process) {
            throw new Exception('Could not launch the Windows update helper script.');
        }

        pclose($process);
    }

    protected function quoteForWindows(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    protected function normalizeVersion(string $version): string
    {
        return ltrim($version, 'v');
    }

    protected function isWindowsAbsolutePath(string $path): bool
    {
        return $this->isWindows() && (bool) preg_match('/^[A-Za-z]:\\\\/', $path);
    }

    protected function isWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
