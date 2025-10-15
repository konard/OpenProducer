# Example 1: Simple Bug Fix Tasks

Create this issue in your repository to spawn multiple bug fix tasks:

```markdown
/spawn-issues
count: 10
template: Fix bug in authentication module
The authentication system has been reporting intermittent failures.
Please investigate and fix the issue.

**Priority**: High
**Affected versions**: v2.0.0+
labels: bug, needs-investigation, high-priority
dry_run: true
unique_by: title
```

**What this does:**
- Creates 10 issues with the same template
- Labels each as "bug", "needs-investigation", and "high-priority"
- Uses dry_run mode to preview before creating
- Deduplicates by title (so all 10 will be created since they're numbered)
