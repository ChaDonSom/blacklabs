<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

use CzProject\GitPhp\Git;
use Illuminate\Support\Str;

uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something(): void
{
    // ..
}

uses()->group('dummy-git-repo')->beforeEach(function () {
    error_reporting(E_ERROR | E_PARSE);
    $this->git = app()->make(Git::class);
    // Force remove the directory
    exec('rm -rf /tmp/test-repo');
    exec('rm -rf /tmp/test-repo-origin');
    $this->repo = $this->git->init('/tmp/test-repo-origin');
    chdir('/tmp/test-repo-origin');
    exec('npm init -y');
    touch('./README.md');
    $this->repo->addAllChanges();
    $this->repo->commit('Initial commit');
    $this->repo->createBranch('forge-production', true);
    $this->repo->createBranch('dev', true);
    file_put_contents('./README.md', 'dev');
    $this->repo->addAllChanges();
    $this->repo->commit('Initial dev commit');
    // Create two branches
    $this->branchOneName = '123-' . Str::kebab(collect(fake()->words())->join('-'));
    $this->repo->createBranch($this->branchOneName, true);
    touch('./README-123.md');
    $this->repo->addAllChanges();
    $this->repo->commit('123 commit');
    $this->repo->checkout('dev');
    $this->branchTwoName = '456-' . Str::kebab(collect(fake()->words())->join('-'));
    $this->repo->createBranch($this->branchTwoName, true);
    touch('./README-456.md');
    $this->repo->addAllChanges();
    $this->repo->commit('456 commit');
    $this->repo->checkout('main');

    $this->repo = $this->git->cloneRepository('/tmp/test-repo-origin', '/tmp/test-repo');
    $this->repo->checkout($this->branchOneName);
    $this->repo->checkout($this->branchTwoName);
    $this->repo->checkout('dev');
    chdir('/tmp/test-repo');
})->afterEach(function () {
    exec('rm -rf /tmp/test-repo');
    exec('rm -rf /tmp/test-repo-origin');
})->in('Feature');
