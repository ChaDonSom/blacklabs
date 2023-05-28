<h1 align="center">Black labs</h1>

<p align="center">
  <a href="https://packagist.org/packages/blacklabs/blacklabs"><img src="https://img.shields.io/packagist/v/blacklabs/blacklabs.svg?label=stable" alt="Latest Stable Version"></a>
</p>

The Black Labs CLI, for our work.

## Install

```sh
composer global require blacklabs/blacklabs
```

## Usage

### Devops

#### Create release branch

```sh
blacklabs devops:create-release-branch 0.14.0.1 1001,1002,1003,884,732,1234
```

This will make a release branch like `release/0.14.0.1-1001-1002-1003-884-732-1234` and merge each of those issues into it.

1. First, it checks out the `dev` branch and pulls it, to make sure it's up to date.
2. Then, it makes a new branch from `dev` like `release/0.14.0.1-1001-1002-1003-884-732-1234`
3. Then, it goes through each issue number provided and tries to find the branch for it.
    1. If it can't find a branch with that issue number in it, it will skip that issue for you to manually merge it in later.
    2. If it finds more than one branch with that issue number in it, it will ask you which one to use.
    3. Then, having found the branch to merge, it merges it from `origin`.

        > :spiral_notepad: Note
        >
        > This means each issue's branch needs to be pushed to origin beforehand.
    4. If it runs into a merge conflict, it will pause and ask you to resolve the merge manually. After resolving it, you can tell it to continue.
4. Once the merging is done, it pushes the branch to `origin`.
5. Then, it creates a PR for the release with each issue listed in its description.
6. Then, it adds a tag as well for that version (and pushes it).

## Contributing

### Deploy process

```sh
php blacklabs app:deploy
```

1. Make sure you're on master
2. Run tests `php blacklabs test`
3. build `php blacklabs app:build` - give it the version
4. Commit it
5. Create a tag for that version
6. Push tags (includes the master branch)
