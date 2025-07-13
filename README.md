# Rodnik.today

**Production:** https://rodnik.today/

## Deploy project to local machine

### Скачайте репозиторий

```
git clone git@github.com:dwcoaching/rodnik.git
cd rodnik
```

### Установите зависимости (в т.ч. Laravel Sail)

Sources (на случай изменения в будущих версиях):

- https://laravel.build/example-app?with=pgsql,redis
- https://laravel.com/docs/11.x/sail#installing-composer-dependencies-for-existing-projects

```
docker run --rm \
    --pull=always \
    -v "$(pwd)":/opt \
    -w /opt \
    laravelsail/php82-composer:latest \
    bash -c "composer install"
```

> Если не получается, прочитайте ошибку. Возможно, придется добавить `--ignore-platform-req=ext-intl`

### Скопируйте `.env` и запустите `Laravel Sail`

```
cp .env.example .env
sail up -d
```

> Обратите внимание на незаполненные переменные в `.env`

### Выполните дефолтные `artisan`-команды

```
sail art key:generate
sail art migrate
sail art storage:link
```

> Если при миграции вы получаете ошибку о том, что БД не создана, используйте команду `sail down -v`
> и после этого снова `sail up -d` (Внимание: это удалит старые БД, если они были)

### Testing

A single command:

```
composer test
```

It will run all tests in parallel. PEST is used for testing. It will required rodnik_today user to be able to create new tables and have all privileges on them.


```
mysql -u root -e "GRANT ALL PRIVILEGES ON \`rodnik_testing%\`.* TO 'rodnik_testing'@'localhost'; FLUSH PRIVILEGES;"
```


Future goal: use it as in https://github.com/nunomaduro/essentials/, i.e.
```
    "scripts": {
        "refactor": "rector",
        "lint": "pint",
        "test:refactor": "rector --dry-run",
        "test:lint": "pint --test",
        "test:types": "phpstan analyse --ansi",
        "test:unit": "pest --colors=always --coverage --parallel",
        "test": [
            "@test:refactor",
            "@test:lint",
            "@test:types",
            "@test:unit"
        ]
    },
```
