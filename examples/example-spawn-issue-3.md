# Example 3: Documentation Tasks with AI Generation

Create this issue to leverage AI for generating varied documentation tasks:

```markdown
/spawn-issues
count: 15
template: Update documentation for new feature
We've released a new feature and need comprehensive documentation.

**Required sections:**
- Overview and use cases
- Installation/setup instructions
- API reference
- Code examples
- Troubleshooting guide

**Target audience:** Developers integrating our API
labels: documentation, help-wanted
assignees: doc-team-lead
rate_limit_per_minute: 20
dry_run: true
unique_by: hash
```

**What this does:**
- Creates 15 documentation issues
- If AI is configured, it will generate variations of the template
- Rate limited to 20 API calls per minute
- Assigns to doc-team-lead
- Uses dry_run to preview AI-generated content first
- Deduplicates by full content hash
