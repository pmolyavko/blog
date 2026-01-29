# Symfony Blog Cache Playground

Небольшой учебный проект для разбора кеширования статей в реальной системе: cache-aside, версионирование ключей, инвалидация, защита от stampede и stale-while-revalidate.

## Архитектура (Hexagonal + DDD)

- **Domain**: агрегаты и бизнес-логика (`Article`), события домена и интерфейсы репозиториев.
- **Application**: Use Cases и порты, DTO и мапперы.
- **Infrastructure**: адаптеры (Doctrine репозиторий, Redis кеш).
- **UserInterface**: контроллеры HTTP (REST), которые вызывают Use Case.

## Что здесь есть

- **Статьи блога** (`Article`) в домене и Doctrine-сущность `ArticleRecord` в инфраструктуре.
- **Use Case**-классы для чтения, листинга и публикации статьи.
- **Redis ArticleCache** (adapter) с:
  - cache-aside для статьи и списков;
  - инвалидацией ключей статьи + списков;
  - версионированием namespace для списков (`articles:list:v{n}:...`);
  - single-flight lock через `SET NX EX` для защиты от stampede;
  - stale-while-revalidate для HTML версии статьи.
- **REST-эндпойнты** для чтения и публикации статьи.

## Быстрый старт (Docker)

```bash
docker compose up --build
```

Приложение будет доступно на `http://localhost:8000`.

## Переменные окружения

- `DATABASE_URL` — подключение к Postgres.
- `REDIS_URL` — подключение к Redis.
- `CACHE_LIST_VERSION_KEY` — ключ версии namespace для списков.

## Основные эндпойнты

### Получить статью по id

```
GET /articles/{id}?lang=ru&role=guest&ab=default
```

### Получить список последних статей

```
GET /articles?page=1&limit=10&lang=ru
```

### Получить HTML-рендер статьи (stale-while-revalidate)

```
GET /articles/{id}/rendered
```

### Публикация статьи (инвалидация кешей)

```
POST /admin/articles/{id}/publish
```

## Ключевые паттерны кеширования

### 1. Cache-aside
- Читаем `article:{id}:{context}` из Redis.
- При промахе грузим из БД и записываем в кеш с TTL.

### 2. Инвалидация
- При publish/update/unpublish:
  - удаляем ключ статьи `article:{id}`;
  - удаляем списки `articles:latest:*`, `articles:tag:{tagId}:*`, `articles:author:{id}:*`;
  - инкрементим `articles:list:version`.

### 3. Версионирование ключей
- Для списков используем `articles:list:v{n}:page:{p}`.
- Любое изменение статьи увеличивает `articles:list:version`, и новые запросы автоматически уходят в новый namespace.

### 4. Stampede защита
- Лок через `SET lock:{key} 1 NX EX 5`.
- Победитель греет кеш, остальные ждут или получают stale.

### 5. Stale-while-revalidate
- Возвращаем немного устаревший контент быстро.
- В фоне перегенерируем и обновляем кеш.

### 6. Что кешировать
- Статья по id.
- Рендер HTML.
- Похожие статьи или списки по фильтрам.
- Учитываем контекст: язык, роль пользователя, AB-тест.

## Что дальше можно добавить

- Очередь (Messenger + Redis) для асинхронной ревалидации.
- Фоновый воркер для прогрева кеша после публикации.
- Отдельные версии ключей для отдельных фильтров (tags/authors).
- Метрики кеша (hit ratio, latency).
