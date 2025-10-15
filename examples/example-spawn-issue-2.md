# Example 2: Component-Specific Tasks

Create this issue to generate tasks for different components:

```markdown
/spawn-issues
count: 5
template: Write unit tests for component
We need comprehensive unit test coverage for this component.

**Requirements:**
- Test coverage should be at least 80%
- Include edge cases
- Mock external dependencies
- Add integration tests where applicable

**Deliverables:**
- Test files in tests/Unit/
- Updated README with test instructions
labels: testing, enhancement, good-first-issue
components_list: Authentication, API, Database, UI, Logging
unique_by: title
dry_run: false
```

**What this does:**
- Creates 5 issues, one for each component in the components_list
- Each issue will incorporate the component name
- Labels as testing/enhancement tasks
- No dry_run, so issues are created immediately
- Good for distributing work across different modules
