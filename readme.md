## ポートフォリオ的なもの

```
バックエンド：PHP, Laravel
フロントエンド：JavaScript, Vue. Nuxt
サーバ： AWS
```

## 作るもの

- TOPページ：HPについての紹介を作成する。

## やったこと

### ローカル開発環境の構築

- 作業PCは、WindowsもしくはMacで行う想定である。

<details><summary>ローカルにDocker環境の構築をする。</summary>

**コンポーザ―を同封したPHP用Dockerfileを用意する。**

```Dockerfile:Dockerfile-php
FROM php:7.3-apache

RUN apt update && apt-get install -y git libzip-dev
RUN docker-php-ext-install pdo_mysql zip

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
ENV COMPOSER_ALLOW_SUPERUSER 1

RUN a2enmod rewrite

WORKDIR /var/backend
```

**docker-compose.ymlを用意する。**

```yaml:docker-compose.yml
version: '3.4'
x-logging:
  &default-logging
  driver: "json-file"
  options:
    max-size: "100k"
    max-file: "3"
volumes:
  mysql_data: { driver: local }
services:

  mysql:
    image: mysql:5.7
    environment:
      MYSQL_ROOT_PASSWORD: password
      MYSQL_DATABASE: laravel
      MYSQL_USER: user
      MYSQL_PASSWORD: password
      TZ: 'Asia/Tokyo'
    volumes:
    - mysql_data:/var/lib/mysql

  backend:
    build:
      context: .
      dockerfile: Dockerfile-php
    logging: *default-logging
    volumes:
    - ./backend:/var/www
    ports:
    - 80:80
```

**コンテナを立ち上げる**

```sh:
$ docker-compose up -d
```
</details>


<details><summary>Laravelを構築する</summary>

```sh:
$ docker-compose exec backend bash
$ composer create-project laravel/laravel=6.* laravel --prefer-dist
$ chmod -R 777 laravel/storage
$ ln -s laravel/public/ ./html
$ cd laravel; composer require barryvdh/laravel-debugbar barryvdh/laravel-ide-helper
$ php artisan ide-helper:generate
```

</details>