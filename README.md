# OpenProducer Issue Spawner Bot

Бот для GitHub, который создаёт множество issues на основе шаблонов из управляющего issue. Написан на PHP 8.1+ с использованием Guzzle HTTP и Composer.

## Основные возможности

- ✅ Создание множества issues по шаблону из одного управляющего issue
- ✅ Поддержка dry-run режима для предпросмотра
- ✅ Дедупликация по title/body/hash
- ✅ Rate limiting с настраиваемой скоростью
- ✅ Rollback (откат) созданных issues
- ✅ Экспоненциальный backoff при ошибках API
- ✅ Логирование всех операций в JSON
- ✅ **Гарантия: бот не изменяет файлы репозитория** - только работа с issues

## Требования

- PHP 8.1 или выше
- Composer
- GitHub Personal Access Token с правами на создание issues

## Установка

### Шаг 1: Клонирование репозитория

```bash
git clone https://github.com/xierongchuan/OpenProducer.git
cd OpenProducer
```

### Шаг 2: Установка зависимостей

```bash
composer install
```

### Шаг 3: Настройка окружения

Скопируйте `.env.example` в `.env`:

```bash
cp .env.example .env
```

Отредактируйте `.env` и укажите ваш GitHub токен:

```env
GITHUB_TOKEN=your_github_personal_access_token_here
THRESHOLD_WARNING=100
DEFAULT_RATE_LIMIT=30
LOGS_DIR=logs
DEBUG=false
```

### Создание GitHub Personal Access Token

1. Перейдите в GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
2. Нажмите "Generate new token (classic)"
3. Выберите scopes:
   - `repo` (полный доступ к приватным репозиториям)
   - Или только `public_repo` для публичных репозиториев
4. Скопируйте токен и вставьте в `.env`

## Использование

### Формат управляющего issue

Создайте issue в вашем репозитории со следующим содержимым:

```markdown
/spawn-issues

count: 20
labels: ["frontend", "agent-task"]
assignees: []
rate_limit_per_minute: 30
dry_run: true
unique_by: title

components_list:
* { "component_name": "users-list", "path": "resources/views/users" }
* { "component_name": "leads-table", "path": "resources/views/leads" }

template:
Title: Перевести компонент {component_name} на Vue3 + Tailwind
Body:
Parent: #{parent_issue}
Context: В репозитории есть компонент {component_name} в папке {path}. Текущее представление — blade + inline js.
Task: Переработать **только фронтенд** компонента на Vue3 (CDN) и Tailwind; **не менять** backend, контроллеры, маршруты и файлы в репозитории.
Acceptance criteria:
1. UI визуально соответствует текущему,
2. Формы отправляют на те же endpoints; CSRF сохраняется,
3. Файлы фронтенда размещаются вне репозитория
FilesToInspect: ["{path}/index.blade.php"]
Priority: medium
EstimatedHours: 4
```

### Параметры конфигурации

- **count**: Количество issues для создания (по умолчанию: 10)
- **labels**: Массив меток для issues (автоматически добавляется `auto-agent-task`)
- **assignees**: Массив GitHub логинов для назначения
- **rate_limit_per_minute**: Ограничение скорости запросов в минуту (по умолчанию: 30)
- **dry_run**: `true` для предпросмотра, `false` для реального создания
- **unique_by**: Метод дедупликации - `title`, `body`, или `hash`
- **components_list**: Массив компонентов для подстановки в шаблон
- **template**: Шаблон с плейсхолдерами `{placeholder}`

### Запуск бота

#### Создание issues

```bash
php bin/bot.php --repo=owner/repo --issue=42
```

Где:
- `owner/repo` - владелец и имя репозитория
- `42` - номер управляющего issue

#### Откат (Rollback)

Для отмены созданных issues используйте run_id из лога:

```bash
php bin/bot.php --rollback=20250315120000_42
```

#### Справка

```bash
php bin/bot.php --help
```

## Примеры использования

### Пример 1: Dry-run для тестирования

Создайте issue с `dry_run: true`:

```markdown
/spawn-issues

count: 5
dry_run: true

template:
Title: Задача {index}
Body:
Это автоматически созданная задача номер {index}
Parent: #{parent_issue}
```

Запустите:

```bash
php bin/bot.php --repo=myorg/myrepo --issue=1
```

Бот опубликует preview в комментарии к issue #1.

### Пример 2: Создание issues для компонентов

```markdown
/spawn-issues

count: 10
dry_run: false
unique_by: title
labels: ["vue3", "migration"]

components_list:
* { "component_name": "UserDashboard", "path": "src/components/users" }
* { "component_name": "OrdersList", "path": "src/components/orders" }
* { "component_name": "Analytics", "path": "src/components/analytics" }

template:
Title: Migrate {component_name} to Vue 3
Body:
Parent: #{parent_issue}

## Task
Migrate the {component_name} component located in {path} to Vue 3 Composition API.

## Requirements
- Use Vue 3 Composition API
- Maintain existing functionality
- Update tests
- No backend changes

Path: `{path}`
```

### Пример 3: Массовое создание с подтверждением

При `count > 100` (по умолчанию) бот потребует подтверждения:

```markdown
/spawn-issues

count: 150
dry_run: false
```

Бот опубликует предупреждение и будет ждать комментария `@bot confirm`.

## Архитектура

```
OpenProducer Issue Spawner
├── bin/
│   └── bot.php              # CLI entry point
├── src/
│   ├── ConfigParser.php     # Парсинг конфигурации из issue
│   ├── GitHubClient.php     # Обёртка для GitHub API
│   └── IssueSpawner.php     # Основная логика создания issues
├── tests/                   # Unit tests
├── logs/                    # Логи запусков (для rollback)
├── composer.json            # Зависимости
├── .env.example             # Пример конфигурации
└── README.md
```

## Безопасность

### Принципы безопасности

1. **Бот не изменяет файлы репозитория** - только API calls для issues
2. Токен хранится в `.env` (не коммитится в git)
3. Проверка прав доступа перед выполнением
4. Rate limiting для предотвращения спама
5. Threshold для подтверждения больших операций
6. Логирование всех действий

### Проверка токена

Убедитесь, что ваш токен имеет **минимальные** необходимые права:

```
✅ repo:issues (создание issues)
✅ repo:public_repo (для публичных репозиториев)
❌ НЕ НУЖЕН: repo:write (push в репозиторий)
```

## Тестирование

Запуск unit tests:

```bash
composer test
```

Или с PHPUnit напрямую:

```bash
./vendor/bin/phpunit
```

## Логи и Rollback

### Структура логов

Каждый запуск создаёт файл `logs/run_{timestamp}_{issue}.json`:

```json
{
  "run_id": "20250315120000_42",
  "control_issue": 42,
  "owner": "myorg",
  "repo": "myrepo",
  "timestamp": "2025-03-15T12:00:00+00:00",
  "created": [
    {
      "id": 12345,
      "number": 43,
      "url": "https://github.com/myorg/myrepo/issues/43",
      "title": "Task 1",
      "created_at": "2025-03-15T12:00:01Z"
    }
  ],
  "errors": []
}
```

### Rollback

Rollback закрывает все issues, созданные в конкретном запуске:

```bash
php bin/bot.php --rollback=20250315120000_42
```

## Troubleshooting

### Проблема: "Bot does not have permissions"

**Решение**: Проверьте, что токен имеет права `repo` или `public_repo`.

### Проблема: "Rate limit exceeded"

**Решение**: Уменьшите `rate_limit_per_minute` в конфигурации или подождите сброса лимита.

### Проблема: "Configuration error"

**Решение**: Проверьте синтаксис управляющего issue. Пример правильного формата:

```markdown
/spawn-issues

count: 10
dry_run: true

template:
Title: Example Task
Body: Description
```

## Ограничения

- Максимум 1000 issues за один запуск (безопасный лимит)
- GitHub API rate limit: ~5000 запросов/час для authenticated users
- Рекомендуемый `rate_limit_per_minute`: 30-60

## Дополнительные возможности

### GitHub App (альтернатива PAT)

Для продвинутого использования можно настроить GitHub App:

1. Создайте GitHub App в Settings → Developer settings → GitHub Apps
2. Укажите permissions: `issues: write`
3. Установите App в ваш репозиторий
4. В `.env` укажите:

```env
GITHUB_APP_ID=123456
GITHUB_APP_PRIVATE_KEY_PATH=/path/to/private-key.pem
GITHUB_INSTALLATION_ID=78901
```

## Вклад в проект

Pull requests приветствуются! Для крупных изменений сначала откройте issue для обсуждения.

## Лицензия

MIT License - см. [LICENSE](LICENSE)

## Контакты

- Issues: https://github.com/xierongchuan/OpenProducer/issues
- Author: xierongchuan

---

**Примечание**: Этот бот является "мета-ботом" - он создаёт задачи для других агентов/ботов, но сам **никогда не изменяет код репозитория**.
