# Примеры управляющих issues

## Пример 1: Простое создание задач с нумерацией

```markdown
/spawn-issues

count: 10
labels: ["todo", "automated"]
assignees: []
rate_limit_per_minute: 30
dry_run: false
unique_by: title

template:
Title: Задача номер {index}
Body:
Parent: #{parent_issue}

## Описание
Это автоматически созданная задача номер {index}

## Что нужно сделать
- [ ] Шаг 1
- [ ] Шаг 2
- [ ] Шаг 3

Создано автоматически ботом.
```

## Пример 2: Миграция компонентов на Vue 3

```markdown
/spawn-issues

count: 20
labels: ["vue3", "migration", "frontend"]
assignees: []
rate_limit_per_minute: 25
dry_run: true
unique_by: title

components_list:
* { "component_name": "UserDashboard", "path": "src/components/users" }
* { "component_name": "OrdersList", "path": "src/components/orders" }
* { "component_name": "ProductCatalog", "path": "src/components/products" }
* { "component_name": "ShoppingCart", "path": "src/components/cart" }
* { "component_name": "CheckoutForm", "path": "src/components/checkout" }

template:
Title: Migrate {component_name} to Vue 3 Composition API
Body:
Parent: #{parent_issue}

## Component Details
- **Name:** {component_name}
- **Location:** `{path}`
- **Priority:** High

## Migration Tasks
1. Convert to Composition API
2. Replace Options API with `setup()`
3. Use `ref()` and `reactive()` for state
4. Update event handlers
5. Test all functionality

## Requirements
- Maintain existing UI/UX
- No breaking changes to props/events
- Update unit tests
- Add TypeScript types (if applicable)

## Files to Review
- `{path}/index.vue`
- `{path}/components/`
- `{path}/__tests__/`

## Acceptance Criteria
- [ ] Component renders correctly
- [ ] All tests passing
- [ ] No console errors
- [ ] Performance not degraded
```

## Пример 3: Создание документации для API endpoints

```markdown
/spawn-issues

count: 50
labels: ["documentation", "api", "auto-agent-task"]
assignees: ["docs-team"]
rate_limit_per_minute: 20
dry_run: false
unique_by: body

components_list:
* { "component_name": "GET /api/users", "path": "app/Http/Controllers/UserController.php" }
* { "component_name": "POST /api/users", "path": "app/Http/Controllers/UserController.php" }
* { "component_name": "GET /api/orders", "path": "app/Http/Controllers/OrderController.php" }
* { "component_name": "POST /api/orders", "path": "app/Http/Controllers/OrderController.php" }

template:
Title: Document API endpoint: {component_name}
Body:
Parent: #{parent_issue}

## Endpoint
`{component_name}`

## Controller Location
`{path}`

## Documentation Tasks
1. Describe endpoint purpose
2. List request parameters
3. Show request examples (curl, JavaScript)
4. Document response format
5. List possible error codes
6. Add authentication requirements

## Template
```markdown
### {component_name}

**Description:** [What this endpoint does]

**Authentication:** Required/Optional

**Parameters:**
| Name | Type | Required | Description |
|------|------|----------|-------------|
| ... | ... | ... | ... |

**Example Request:**
\```bash
curl -X GET https://api.example.com/...
\```

**Example Response:**
\```json
{
  "data": ...
}
\```

**Error Codes:**
- 400: Bad Request
- 401: Unauthorized
- 404: Not Found
```

## Acceptance Criteria
- [ ] All sections filled
- [ ] Examples tested and working
- [ ] Added to API documentation site
```

## Пример 4: Тестирование с dry-run

```markdown
/spawn-issues

count: 5
labels: ["test"]
assignees: []
rate_limit_per_minute: 60
dry_run: true
unique_by: hash

template:
Title: Test Task {index}
Body:
Parent: #{parent_issue}

This is a test issue number {index}.

**Note:** This is a dry run test. No actual issues will be created until dry_run is set to false.
```

**Результат:** Бот создаст preview в комментарии, но не создаст реальные issues.

## Пример 5: Рефакторинг с большим количеством задач

```markdown
/spawn-issues

count: 150
labels: ["refactoring", "code-quality"]
assignees: []
rate_limit_per_minute: 30
dry_run: false
unique_by: title

template:
Title: Refactor module {index}
Body:
Parent: #{parent_issue}

## Module: module-{index}

### Refactoring Goals
- Improve code readability
- Add type hints
- Remove deprecated methods
- Update documentation

### Checklist
- [ ] Add type hints
- [ ] Remove dead code
- [ ] Update tests
- [ ] Update docs
```

**Примечание:** Так как count > 100, бот потребует подтверждения командой `@bot confirm` в комментарии.

## Пример 6: Перевод интерфейса на другой язык

```markdown
/spawn-issues

count: 10
labels: ["i18n", "translation"]
assignees: ["translator"]
rate_limit_per_minute: 20
dry_run: false
unique_by: title

components_list:
* { "component_name": "Login Page", "path": "resources/lang/en/auth.php" }
* { "component_name": "Dashboard", "path": "resources/lang/en/dashboard.php" }
* { "component_name": "Settings", "path": "resources/lang/en/settings.php" }
* { "component_name": "Profile", "path": "resources/lang/en/profile.php" }
* { "component_name": "Notifications", "path": "resources/lang/en/notifications.php" }

template:
Title: Translate {component_name} to Russian
Body:
Parent: #{parent_issue}

## Translation Task

**Component:** {component_name}
**Source File:** `{path}`
**Target Language:** Russian (ru)

## Instructions
1. Create file `{path}` with `en` replaced by `ru`
2. Translate all strings while keeping keys intact
3. Test in UI to ensure proper display
4. Check for text overflow issues

## Important Notes
- Keep variable placeholders intact (e.g., `:name`, `{count}`)
- Maintain formatting (HTML tags, markdown)
- Use formal Russian tone
- Review pluralization rules

## Acceptance Criteria
- [ ] All strings translated
- [ ] No formatting broken
- [ ] UI displays correctly
- [ ] Reviewed by native speaker
```

## Пример 7: Создание E2E тестов

```markdown
/spawn-issues

count: 30
labels: ["testing", "e2e", "playwright"]
assignees: []
rate_limit_per_minute: 25
dry_run: false
unique_by: title

components_list:
* { "component_name": "User Registration", "path": "tests/e2e/auth.spec.ts" }
* { "component_name": "Login Flow", "path": "tests/e2e/auth.spec.ts" }
* { "component_name": "Password Reset", "path": "tests/e2e/auth.spec.ts" }
* { "component_name": "Profile Update", "path": "tests/e2e/profile.spec.ts" }
* { "component_name": "Create Order", "path": "tests/e2e/orders.spec.ts" }

template:
Title: Write E2E test for {component_name}
Body:
Parent: #{parent_issue}

## Test Scenario: {component_name}

**Test File:** `{path}`

## Test Steps
1. Navigate to page
2. Interact with UI elements
3. Verify expected behavior
4. Check edge cases

## Test Cases to Cover
- Happy path
- Validation errors
- Edge cases
- Error handling

## Example Test Structure
\```typescript
test('{component_name} - happy path', async ({ page }) => {
  // Test implementation
});

test('{component_name} - validation', async ({ page }) => {
  // Test implementation
});
\```

## Acceptance Criteria
- [ ] All test cases implemented
- [ ] Tests pass on CI
- [ ] Code coverage > 80%
- [ ] Screenshots captured
```

---

## Как использовать примеры

1. Скопируйте пример в новый issue вашего репозитория
2. Измените параметры под ваши нужды
3. Запустите бота:
   ```bash
   php bin/bot.php --repo=owner/repo --issue=ISSUE_NUMBER
   ```
4. Проверьте результат в комментариях к issue

## Советы

- **Всегда начинайте с `dry_run: true`** для проверки
- Используйте **осмысленные labels** для фильтрации
- Настройте **rate_limit_per_minute** чтобы не превышать лимиты GitHub
- **Проверяйте unique_by** чтобы избежать дубликатов
- Для больших batch (>100) будьте готовы подтвердить командой `@bot confirm`
