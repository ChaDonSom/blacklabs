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
            // Use GitHub GraphQL API to get linked branches for the issue
            $query = <<<'GRAPHQL'
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
GRAPHQL;

            // Get the repository owner and name from the current git remote
            $remote = $this->runProcess('git config --get remote.origin.url');
            if (! preg_match('/github\.com[\/:]([^\/]+)\/([^\/\.]+)(\.git)?/', $remote, $matches)) {
                return null;
            }
            $owner = $matches[1];
            $repo = $matches[2];

            $result = $this->runProcess(
                "gh api graphql -f query='{$query}' -F owner='{$owner}' -F repo='{$repo}' -F issueNumber={$issueNumber} --jq '.data.repository.issue.linkedBranches.nodes[0].ref.name'"
            );

            $branchName = trim($result);

            return strlen($branchName) > 0 && $branchName !== 'null' ? $branchName : null;
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
            // Use GitHub GraphQL API to search for issues linked to this branch
            $query = <<<'GRAPHQL'
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
GRAPHQL;

            // Get the repository owner and name from the current git remote
            $remote = $this->runProcess('git config --get remote.origin.url');
            if (! preg_match('/github\.com[\/:]([^\/]+)\/([^\/\.]+)(\.git)?/', $remote, $matches)) {
                return null;
            }
            $owner = $matches[1];
            $repo = $matches[2];

            // Ensure branch name is in the format refs/heads/{branch}
            $qualifiedBranchName = str_starts_with($branchName, 'refs/') ? $branchName : "refs/heads/{$branchName}";

            $result = $this->runProcess(
                "gh api graphql -f query='{$query}' -F owner='{$owner}' -F repo='{$repo}' -F branchName='{$qualifiedBranchName}' --jq '.data.repository.ref.associatedPullRequests.nodes[0].closingIssuesReferences.nodes[0].number'"
            );

            $issueNumber = trim($result);

            return strlen($issueNumber) > 0 && $issueNumber !== 'null' ? $issueNumber : null;
        } catch (\Exception $e) {
            // GitHub CLI not available or not authenticated, or no linked issue found
            return null;
        }
    }
}
