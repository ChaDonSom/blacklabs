<h1 align="center">Black labs</h1>

<p align="center">
  <a href="https://packagist.org/packages/blacklabs/blacklabs"><img src="https://img.shields.io/packagist/v/blacklabs/blacklabs.svg?label=stable" alt="Latest Stable Version"></a>
</p>

The Black Labs CLI, for our work.

## Install

```sh
composer global require blacklabs/blacklabs
```

### Updating

```sh
composer global update blacklabs/blacklabs
```

```sh
blacklabs self-update
```

This command has been lately throwing an error about `zlib` or something. However, it doesn't appear to cause any problems.

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

### Update site branch and deploy

```sh
blacklabs devops:update-site-branch-and-deploy
```

This command pings Forge (if you haven't given the app a Forge API token yet, do that first: `blacklabs app:store-forge-api-token <token>`).

1. First, it asks you which site to deploy to, out of the list of sites from Forge, filtered by sites that have the 'console' tag.
2. Then, it asks you which branch to deploy. For the options here, it uses `git branch -r` (`-r` for 'remotes', showing only the branches that are pushed to origin).
3. Then, it pings Forge to update that site's branch to the branch you chose.
4. Then, it pings Forge again to initiate deployment for that site.

## Contributing

### Deploy process

```sh
php blacklabs app:deploy
```

This command does:

1. Make sure you're on master
2. Run tests `php blacklabs test`
3. build `php blacklabs app:build` - give it the version
4. Commit it
5. Create a tag for that version
6. Push tags (includes the master branch)
