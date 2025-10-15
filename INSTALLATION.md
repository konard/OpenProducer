# Руководство по установке OpenProducer Issue Spawner Bot

Подробная инструкция по установке и настройке бота для создания множественных issues в GitHub репозиториях.

## Системные требования

- **PHP**: версия 8.1 или выше
- **Composer**: менеджер зависимостей для PHP
- **Git**: для клонирования репозитория
- **GitHub Account**: с правами на создание issues в целевом репозитории

### Проверка версии PHP

```bash
php -v
```

Ожидаемый вывод (версия должна быть >= 8.1):
```
PHP 8.1.x (cli) ...
```

### Установка PHP 8.1+ (если необходимо)

**Ubuntu/Debian:**
```bash
sudo apt update
sudo apt install php8.1 php8.1-cli php8.1-curl php8.1-mbstring php8.1-xml
```

**macOS (Homebrew):**
```bash
brew install php@8.1
```

**Windows:**
Скачайте PHP с https://windows.php.net/download/

### Установка Composer

**Linux/macOS:**
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

**Windows:**
Скачайте установщик с https://getcomposer.org/download/

Проверка:
```bash
composer --version
```

## Установка бота

### Шаг 1: Клонирование репозитория

```bash
git clone https://github.com/xierongchuan/OpenProducer.git
cd OpenProducer
```

Или, если вы работаете с форком:
```bash
git clone https://github.com/YOUR_USERNAME/OpenProducer.git
cd OpenProducer
```

### Шаг 2: Установка зависимостей

```bash
composer install
```

Эта команда установит:
- `guzzlehttp/guzzle` - HTTP клиент для работы с GitHub API
- `vlucas/phpdotenv` - для работы с переменными окружения
- `symfony/console` - для CLI интерфейса
- `phpunit/phpunit` - для тестирования

### Шаг 3: Настройка конфигурации

Скопируйте файл-пример `.env.example` в `.env`:

```bash
cp .env.example .env
```

Откройте `.env` в текстовом редакторе:

```bash
nano .env
# или
vim .env
# или
code .env  # VS Code
```

### Шаг 4: Получение GitHub токена

Вам понадобится Personal Access Token (PAT) для аутентификации с GitHub API.

#### Создание Personal Access Token

1. Перейдите на https://github.com/settings/tokens
2. Нажмите **"Generate new token"** → **"Generate new token (classic)"**
3. Дайте токену описательное имя, например: `OpenProducer Issue Spawner`
4. Выберите срок действия (рекомендуется: 90 дней или No expiration для постоянного использования)
5. Выберите следующие scopes (права):

**Для публичных репозиториев:**
- ✅ `public_repo` - Доступ к публичным репозиториям

**Для приватных репозиториев:**
- ✅ `repo` - Полный доступ к приватным репозиториям
  - ✅ `repo:status`
  - ✅ `repo_deployment`
  - ✅ `public_repo`
  - ✅ `repo:invite`
  - ✅ `security_events`

6. Нажмите **"Generate token"**
7. **ВАЖНО:** Скопируйте токен сразу! Он не будет показан повторно.

#### Добавление токена в .env

Откройте `.env` и вставьте токен:

```env
GITHUB_TOKEN=ghp_your_token_here_xxxxxxxxxxxxx
```

Полный пример `.env`:

```env
# GitHub Authentication
GITHUB_TOKEN=ghp_abcdefghijklmnopqrstuvwxyz0123456789

# Bot Configuration
THRESHOLD_WARNING=100
DEFAULT_RATE_LIMIT=30
LOGS_DIR=logs
DEBUG=false
```

### Шаг 5: Проверка установки

Запустите справку бота:

```bash
php bin/bot.php --help
```

Ожидаемый вывод:
```
OpenProducer Issue Spawner Bot
===============================

Usage:
  php bin/bot.php --repo=owner/repo --issue=123
  php bin/bot.php --rollback=run_id

Options:
  --repo=owner/repo    Repository in format owner/repo
  --issue=123          Control issue number
  --rollback=run_id    Rollback a previous run
  --help               Show this help message
...
```

### Шаг 6: Проверка прав доступа

Создайте тестовый issue в вашем репозитории с содержимым:

```markdown
/spawn-issues

count: 1
dry_run: true

template:
Title: Test Issue
Body:
This is a test issue to verify bot installation.
Parent: #{parent_issue}
```

Запустите бота с dry-run:

```bash
php bin/bot.php --repo=YOUR_USERNAME/YOUR_REPO --issue=ISSUE_NUMBER
```

Если всё настроено правильно, бот опубликует preview в комментарии к issue.

## Альтернативная настройка: GitHub App

Для продвинутого использования вы можете создать GitHub App вместо использования Personal Access Token.

### Создание GitHub App

1. Перейдите в Settings → Developer settings → GitHub Apps
2. Нажмите "New GitHub App"
3. Заполните форму:
   - **Name**: OpenProducer Issue Spawner
   - **Homepage URL**: https://github.com/xierongchuan/OpenProducer
   - **Webhook**: отключить (снять галочку "Active")
   - **Permissions**:
     - Repository permissions:
       - Issues: Read & Write
       - Metadata: Read-only

4. Создайте приложение
5. Сгенерируйте Private Key (внизу страницы)
6. Установите App в ваш репозиторий
7. Запишите:
   - App ID
   - Installation ID (можно найти в URL при установке)
   - Путь к private key файлу

### Настройка .env для GitHub App

```env
# GitHub App credentials
GITHUB_APP_ID=123456
GITHUB_APP_PRIVATE_KEY_PATH=/path/to/your-app-name.2025-03-15.private-key.pem
GITHUB_INSTALLATION_ID=78901

# Закомментируйте или удалите GITHUB_TOKEN
# GITHUB_TOKEN=...
```

**Примечание:** Поддержка GitHub App будет добавлена в будущей версии. На данный момент используйте Personal Access Token.

## Структура проекта

После установки структура проекта будет выглядеть так:

```
OpenProducer/
├── bin/
│   └── bot.php                 # CLI entry point
├── src/
│   ├── ConfigParser.php        # Парсинг конфигурации
│   ├── GitHubClient.php        # GitHub API клиент
│   └── IssueSpawner.php        # Основная логика
├── tests/
│   └── ConfigParserTest.php    # Unit tests
├── examples/
│   └── control-issue-example.md # Примеры
├── logs/                        # Создастся автоматически
├── vendor/                      # Composer dependencies
├── .env                         # Ваша конфигурация (не в git)
├── .env.example                # Шаблон конфигурации
├── .gitignore
├── composer.json
├── phpunit.xml
├── README.md
├── INSTALLATION.md
└── LICENSE
```

## Тестирование установки

### Запуск Unit Tests

```bash
composer test
```

Или:

```bash
./vendor/bin/phpunit
```

Все тесты должны пройти успешно:

```
PHPUnit 10.x.x

..........                                                 10 / 10 (100%)

Time: 00:00.123, Memory: 10.00 MB

OK (10 tests, 25 assertions)
```

### Тестовый запуск бота

1. Создайте issue в вашем репозитории (пример в `examples/control-issue-example.md`)
2. Убедитесь, что `dry_run: true`
3. Запустите бота:

```bash
php bin/bot.php --repo=YOUR_USERNAME/YOUR_REPO --issue=ISSUE_NUMBER
```

4. Проверьте комментарий с preview в issue

## Устранение проблем

### Ошибка: "GITHUB_TOKEN not set"

**Решение:** Убедитесь, что `.env` файл существует и содержит `GITHUB_TOKEN=...`

### Ошибка: "Bot does not have permissions"

**Решение:**
- Проверьте, что токен имеет правильные scopes (repo или public_repo)
- Убедитесь, что токен не истёк (проверьте на https://github.com/settings/tokens)

### Ошибка: "Call to undefined function"

**Решение:** Убедитесь, что установлены все PHP расширения:
```bash
php -m | grep -E 'curl|mbstring|json'
```

### Ошибка: "Composer dependencies not found"

**Решение:** Запустите `composer install` ещё раз

### Ошибка: "Permission denied: bin/bot.php"

**Решение:** Сделайте файл исполняемым:
```bash
chmod +x bin/bot.php
```

### Debug режим

Для получения подробных логов ошибок включите debug режим в `.env`:

```env
DEBUG=true
```

И запустите бота снова.

## Обновление

Для обновления бота до новой версии:

```bash
git pull origin main
composer update
```

## Удаление

Для удаления бота:

```bash
cd ..
rm -rf OpenProducer
```

Не забудьте также:
1. Удалить GitHub токен (если больше не используется): https://github.com/settings/tokens
2. Удалить GitHub App (если была создана)

## Дополнительная помощь

- **Issues**: https://github.com/xierongchuan/OpenProducer/issues
- **Documentation**: См. README.md и examples/
- **GitHub API Docs**: https://docs.github.com/en/rest

## Безопасность

- **Никогда не коммитьте `.env` файл** в git (он уже в .gitignore)
- **Не делитесь токеном** с другими
- **Используйте токены с минимальными правами** (только то, что нужно)
- **Регулярно ротируйте токены** (меняйте каждые 90 дней)
- **Удаляйте неиспользуемые токены**

---

**Готово!** Теперь ваш OpenProducer Issue Spawner Bot установлен и готов к использованию.
