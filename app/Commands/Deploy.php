<?php

namespace App\Commands;

use CzProject\GitPhp\Git;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class Deploy extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'app:deploy';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Deploy the blacklabs CLI to Packagist.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Git $git)
    {
        // If we're not in the ChaDonSom/blacklabs repo, exit
        $url = trim($this->runProcess('git config --get remote.origin.url'));
        if ($url !== 'git@github.com:ChaDonSom/blacklabs.git') {
            $this->error('You must be in the ChaDonSom/blacklabs repo to run this command.');
            return;
        }

        // If the working tree is dirty, exit
        $this->info('Checking working tree...');
        $dirty = trim($this->runProcess('git status --porcelain'));
        if ($dirty) {
            $this->error('Working tree is dirty. Commit or stash your changes and try again.');
            return;
        }

        // If master isn't pushed, exit
        $this->info('Checking remote...');
        $this->runProcess('git fetch');
        $ahead = trim($this->runProcess('git rev-list --count --left-only HEAD...@{u}'));
        if ($ahead) {
            $this->error('Local master is ahead of remote. Push your changes and try again.');
            return;
        }

        $repo = $git->open(getcwd());
        $this->info('Deploying blacklabs CLI to Packagist.');
        $repo->checkout('master');

        $this->info('Running tests...');
        $this->runProcess('php blacklabs test');

        $this->info('Getting version number...');
        $version = trim($this->runProcess('git describe --abbrev=0 --tags'));
        $this->info($version);
        $version = match ($this->choice('Will this be a major, minor, or patch release?', ['major', 'minor', 'patch'], 2)) {
            'major' => $this->incrementMajor($version),
            'minor' => $this->incrementMinor($version),
            'patch' => $this->incrementPatch($version),
        };
        $this->info("New version number: {$version}");

        $this->info('Building Phar...');
        $this->runProcess('php blacklabs app:build --build-version=' . $version);

        $this->info('Committing changes...');
        $repo->addFile('builds/blacklabs');
        $repo->commit('Build Phar for version ' . $version);

        $this->info('Tagging release...');
        $repo->createTag($version, [ '-m' => "Release {$version}" ]);
        $this->info('Pushing tag...');
        $repo->push();
        $repo->push('origin', ['--tags']);

        $this->info('Done.');
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }

    public function runProcess($command) {
        $result = Process::run($command);
        if (!$result->successful()) {
            throw new \Exception($result->errorOutput() ?: $result->output());
        }
        return $result->output();
    }

    public function incrementMajor($version) {
        $versionArray = explode('.', $version);
        $versionArray[0]++;
        $versionArray[1] = 0;
        $versionArray[2] = 0;
        return implode('.', $versionArray);
    }

    public function incrementMinor($version) {
        $versionArray = explode('.', $version);
        $versionArray[1]++;
        $versionArray[2] = 0;
        return implode('.', $versionArray);
    }

    public function incrementPatch($version) {
        $versionArray = explode('.', $version);
        $versionArray[2]++;
        return implode('.', $versionArray);
    }
}
