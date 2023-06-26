<h1 align="center">Black labs</h1>

<p align="center">
  <a href="https://packagist.org/packages/blacklabs/blacklabs"><img src="https://img.shields.io/packagist/v/blacklabs/blacklabs.svg?label=stable" alt="Latest Stable Version"></a>
</p>

The Black Labs CLI, for our work.

## Requirements

1. You must have `git` istalled on your machine

    ```sh
    sudo apt install git-all
    ```

2. You must have `gh` installed for the `create-release-branch` command (it makes a PR for that branch)

    See [the `gh` install instructions for Linux](https://github.com/cli/cli/blob/trunk/docs/install_linux.md)

3. For the forge commands, you'll need to run `blacklabs app:store-forge-api-token <token>` first. It will be saved to disk, so you won't need to run it again (until your token expires).

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
blacklabs create-release-branch 0.14.0.1 1001,1002,1003,884,732,1234
```

This will make a release branch like `release/0.14.0.1-1001-1002-1003-884-732-1234` and merge each of those issues into it.

1. First, it checks out the `dev` branch and pulls it, to make sure it's up to date.
2. Then, it makes a new branch from `dev` like `release/0.14.0.1-1001-1002-1003-884-732-1234`
    1. If it finds an existing branch by that name, it will ask you if you want to delete it and remake it. If you respond affirmative, it will do so, if not, it will exit.
3. Then, it goes through each issue number provided and tries to find the branch for it.
    1. If it can't find a branch with that issue number in it, it will skip that issue for you to manually merge it in later.
    2. If it finds more than one branch with that issue number in it, it will ask you which one to use.
    3. Then, having found the branch to merge, it merges it from `origin`.

        > **Note**
        >
        > This means each issue's branch needs to be pushed to origin beforehand.
    4. If it runs into a merge conflict, it will pause and ask you to resolve the merge manually. After resolving it, you can tell it to continue.
4. Once the merging is done, it pushes the branch to `origin`.
5. Then, it creates a PR for the release with each issue listed in its description.
6. Then, it adds a tag as well for that version (and pushes it).

### Show site branches

```sh
blacklabs show-site-branches
```

This command pings Forge for our servers with the 'console' tag, then displays all their sites along with the git branch they're currently assigned to.

This is mainly helpful to get a quick glance while it's a little cumbersome to click through Forge to see it.

### Update site branch and deploy

```sh
blacklabs update-site-branch-and-deploy
```

> **Note**
>
> This command pings Forge (if you haven't given the app a Forge API token yet, do that first: `blacklabs app:store-forge-api-token <token>`).

1. First, it asks you which site to deploy to, out of the list of sites from Forge, filtered by sites that have the 'console' tag.
2. Then, it asks you which branch to deploy. For the options here, it uses `git branch -r` (`-r` for 'remotes', showing only the branches that are pushed to origin).
3. Then, it pings Forge to update that site's branch to the branch you chose.
4. Then, it pings Forge again to initiate deployment for that site.

### Deploy to production

```sh
blacklabs deploy-to-production
```

1. It will warn you, and you have to type `forge-production` to make it go through.
2. Then, it will ask you which branch to deploy, if you didn't provide the branch as the first argument.
3. Then, it checks out `forge-production`, and merges your chosen branch in.
4. Then, it pushes `forge-production`.
5. Then, it checks out `dev` and merges `forge-production` into it.
6. Then, it pushes `dev`.

### Remerge release branch

```sh
blacklabs merge-and-increment-tag release/0.14.0.1-1002-1003-1120-843-034 843,034
```

This just helps with merging in the issue branch, then incrementing the tag number. The Forge server handles auto-deploy on its own for this workflow. So, this command would only:

1. Merge the specified issue branches in
2. Increment the tag's deploy number (from the one found in the specified branch)
    1. find the tag from the branch
    2. find the latest tag that matches that tag up to the hotfix number
    3. increment the deploy number of it
3. Push the branch and the tags

### Add or Remove Issues from Site

```sh
blacklabs site add-issues blacklabtesting.com 388,3843
```

1. It grabs the branch currently deployed to that site.
2. It gets the tag from the branch and increments the `deploy` number of it (the 4th number, for example:
    
    ```
    0.13.3.1
           ^
    ```

    ).
3. It comes up with a new list of issues based on the existing list in the branch name, and the ones you listed to add or remove.
4. It creates a new release branch (see [Create Release Branch](#create-release-branch))
5. It updates the given site's branch and triggers a deployment on it (see [Update Site Branch and Deploy](#update-site-branch-and-deploy))

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
