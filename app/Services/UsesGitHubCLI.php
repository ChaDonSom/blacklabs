<?php

namespace App\Services;

trait UsesGitHubCLI
{
    use RunsProcesses;

    /**
     * Get the branch associated with a GitHub issue using the GitHub CLI.
     * Returns the branch name if found, null otherwise.
     *
     * @param  int|string  $issueNumber  The issue number
     * @return string|null The branch name or null if not found
     */
    public function getBranchForIssue($issueNumber): ?string
    {
        try {
            [$owner, $repo] = $this->getGitHubOwnerAndRepo();
            if (! $owner || ! $repo) {
                return null;
            }

            // Validate issue number is actually a number to prevent injection
            if (! is_numeric($issueNumber)) {
                return null;
            }

            // Use GitHub GraphQL API to get linked branches for the issue
            // Write query to a temporary file to avoid command injection
            $queryFile = tempnam(sys_get_temp_dir(), 'gh_query_');
            if ($queryFile === false) {
                return null;
            }

            file_put_contents($queryFile, <<<'GRAPHQL'
query($owner: String!, $repo: String!, $issueNumber: Int!) {
  repository(owner: $owner, name: $repo) {
    issue(number: $issueNumber) {
      number
      title
      linkedBranches(first: 10) {
        nodes {
          ref {
            name
          }
        }
      }
    }
  }
}
GRAPHQL);

            try {
                $jqExpression = '.data.repository.issue.linkedBranches.nodes[0].ref.name';

                $result = $this->runProcess(
                    'gh api graphql '.
                    '-f query=@'.escapeshellarg($queryFile).' '.
                    '-F owner='.escapeshellarg($owner).' '.
                    '-F repo='.escapeshellarg($repo).' '.
                    '-F issueNumber='.escapeshellarg($issueNumber).' '.
                    '--jq '.escapeshellarg($jqExpression)
                );

                return $this->validateAndReturnResult($result);
            } finally {
                unlink($queryFile);
            }
        } catch (\Exception $e) {
            // GitHub CLI not available or not authenticated, or no linked branch found
            return null;
        }
    }

    /**
     * Get the issue number associated with a GitHub branch using the GitHub CLI.
     * Returns the issue number if found, null otherwise.
     *
     * @param  string  $branchName  The branch name
     * @return string|null The issue number or null if not found
     */
    public function getIssueForBranch(string $branchName): ?string
    {
        try {
            [$owner, $repo] = $this->getGitHubOwnerAndRepo();
            if (! $owner || ! $repo) {
                return null;
            }

            // Ensure branch name is in the format refs/heads/{branch}
            $qualifiedBranchName = str_starts_with($branchName, 'refs/') ? $branchName : "refs/heads/{$branchName}";

            // Use GitHub GraphQL API to search for issues linked to this branch
            // Write query to a temporary file to avoid command injection
            $queryFile = tempnam(sys_get_temp_dir(), 'gh_query_');
            if ($queryFile === false) {
                return null;
            }

            file_put_contents($queryFile, <<<'GRAPHQL'
query($owner: String!, $repo: String!, $branchName: String!) {
  repository(owner: $owner, name: $repo) {
    ref(qualifiedName: $branchName) {
      associatedPullRequests(first: 1, states: [OPEN, CLOSED, MERGED]) {
        nodes {
          closingIssuesReferences(first: 10) {
            nodes {
              number
            }
          }
        }
      }
    }
  }
}
GRAPHQL);

            try {
                $jqExpression = '.data.repository.ref.associatedPullRequests.nodes[0].closingIssuesReferences.nodes[0].number';

                $result = $this->runProcess(
                    'gh api graphql '.
                    '-f query=@'.escapeshellarg($queryFile).' '.
                    '-F owner='.escapeshellarg($owner).' '.
                    '-F repo='.escapeshellarg($repo).' '.
                    '-F branchName='.escapeshellarg($qualifiedBranchName).' '.
                    '--jq '.escapeshellarg($jqExpression)
                );

                return $this->validateAndReturnResult($result);
            } finally {
                unlink($queryFile);
            }
        } catch (\Exception $e) {
            // GitHub CLI not available or not authenticated, or no linked issue found
            return null;
        }
    }

    /**
     * Extract GitHub owner and repo from git remote URL.
     * Supports both HTTPS (https://github.com/owner/repo.git) and
     * SSH (git@github.com:owner/repo.git) URL formats.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function getGitHubOwnerAndRepo(): array
    {
        try {
            $remote = $this->runProcess('git config --get remote.origin.url');
            // Match both HTTPS and SSH GitHub URLs
            if (preg_match('/github\.com[\/:]([^\/]+)\/([^\/\.]+)(\.git)?/', $remote, $matches)) {
                return [$matches[1], $matches[2]];
            }
        } catch (\Exception $e) {
            // Unable to get git remote
        }

        return [null, null];
    }

    /**
     * Validate and return the result from GitHub CLI.
     *
     * @param  string  $result  The raw result from gh CLI
     * @return string|null The validated result or null
     */
    private function validateAndReturnResult(string $result): ?string
    {
        $trimmed = trim($result);

        return strlen($trimmed) > 0 && $trimmed !== 'null' ? $trimmed : null;
    }
}
