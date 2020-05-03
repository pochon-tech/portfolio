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

<details><summary>Top画面を作成する</summary>

- Laravel側ではBladeテンプレートをメインに画面を製作する。
- CSSはbootstrapを使用する。 https://getbootstrap.com/docs/4.1 
- `welcome.blade.php`をトップ画面とする。

</details>

<details><summary>マルチAuth認証を作成する</summary>

- User,Adminの二種類でAuth認証を実装する。

**Modelのディレクトリ構成**
```
models
  ├── user
  ├── admin
```

**Controllerのディレクトリ構成**
```
controllers
　├── Admin
　│   ├── Auth
　│   │   ├── LoginController.php
　│   │   ├── RegisterController.php
　│   └── HomeController.php
　├── User
　│   ├── Auth
　│   │   ├── LoginController.php
　│   │   ├── RegisterController.php
　│   └── HomeController.php
　└── Controller.php
```
**Viewsのディレクトリ構成**
```
views
  ├── user
  │   ├── auth
  │   │   ├── login.blade.php
  │   │   └── register.blade.php
  │   └── home.blade.php
  │
  ├── admin
  │   ├── auth
  │   │   ├── login.blade.php
  │   │   └── register.blade.php
  │   └── home.blade.php
  │
  └── layouts
      ├── user
      │    └── app.blade.php
      │
      └── admin
          └── app.blade.php
```

**モデルを作成する**

- `.env`ファイルを修正して、Mysqlと接続できるようにしておく。
- `php artisan migrate`を実行して、マイグレーションファイルを作成する。このタイミングで
- Admin用のモデルを作成する。`Models`ディレクトリ配下に作成されるようにする。
```
$ php artisan make:model Models/Admin -m
```

- 上記で作成されるマイグレーションファイルを、標準の`create_users_table.php`と同じようなデータ構成で修正する
```php:
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAdminsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('admins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('admins');
    }
}
```
- `create_admins_table`を上記のように修正できたら、`php artisan migrate`を実行し、Adminsテーブルを作成する。
- ※マイグレーションファイルの命名ルールは以下の通りである。
  - `YYYY_MM_DD_HHIISS`: 日付の小さいものから実行される
  - `[create/update]_[テーブル名]_table`: そのままクラス名に利用される。
  - 名称は、実はなんでも良いがマイグレーションの実行内容がわかる名前をつけたほうが良い。

**ダミーデータを用意するためにSeederを作成する**

```sh:
$ php artisan make:seeder UsersTableSeeder
$ php artisan make:seeder AdminsTableSeeder
```
- 上記を実行すると、`database/seeds`配下にSeederクラスが作成される。
- 下記のようなダミーデータを各ファイルのrunメソッドに定義する。

```php:
DB::table('users')->insert([
    'name'              => 'user',
    'email'             => 'user@example.com',
    'password'          => Hash::make('user'),
    'remember_token'    => Str::random(10),
]);
DB::table('admins')->insert([
    'name'              => 'admin',
    'email'             => 'admin@example.com',
    'password'          => Hash::make('admin'),
    'remember_token'    => Str::random(10),
]); 
```
- 上記のダミーデータ作成処理が同時に実行されるように、`database/seeds/DatabaseSeeder.php`のrunメソッドに下記を追記する。

```php:
$this->call([
    UsersTableSeeder::class,
    AdminsTableSeeder::class,
]);
```

- 上記までの準備が出来たら、`$ php artisan db:seed`を実行してダミーデータを実際に準備する。

**Userモデルも階層にあわせるようにする**

- Userモデルは標準のままだと、`app`ディレクトリ直下にUser.phpとして設置されるので、`app/Models`配下に移動させておく。
- 移動させたら`User.php`の先頭のほうに定義している`namespace`も`namespace App\Models;`に忘れずに書き換えておく。
- `User.php`をuseしている下記2点もも修正する。
  - app/Http/Controllers/Auth/RegisterController.php
  - config/auth.php

**Admin.phpを実装する**

- 自動生成した`Admin.php`は、下記のように単純なモデル (Eloquent継承クラス) になっていることに注意。
```php:
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
}
```
- `User.php`同様に、`Authenticatable`を継承させるように修正する。
```php:
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
```

**Guardの追加** 

- `config/auth.php`にAdminの認証方式を追加する。
- 変更点は下記。
  - デフォルトの認証 (`defaults`) を修正する。標準のままだと「web」になっているので、分かりづらいので「user」に返る。
  - `guards`を変更・追加する。「web」を「user」というGuard名に変更。「admin」を追加 (userとほぼ同じ、providerだけ`admins`にする)。
  - `providers`に追加。「users」と同じような構成で「admins」を追加。「model」は「`App\Models\Admin::class`」を指定する。
  - `passwords`に追加。「users」と同じような構成で「admins」を追加。「provider」だけ「`admins`」にする。

**HomeControllerの作成**

- Controllers配下にAdminとUserディレクトリを作成
- それぞれのディレクトリに`HomeController`を作成する。`HomeController`は、ログイン後に表示する画面出力用。
```sh:
php artisan make:controller Admin/HomeController --resource
php artisan make:controller User/HomeController --resource
```
- 作成したら、`__construct`メソッドと`index`メソッドの実装を行う。
```php:
    public function __construct()
    {
        // User/HomeControllerの場合
        $this->middleware('auth:user');
        // Admin/HomeControllerの場合
        $this->middleware('auth:admin');
    }

    public function index()
    {
        return view('user.home');
    }
```

**ルーティング設定を行う**

- `routes/web.php`に、作成したControllerとのパスを紐づとAuth認証をそれぞれ指定
```php:
// User
Route::namespace('User')->prefix('user')->name('user.')->group(function () {

    // ログイン認証関連
    Auth::routes([
        'register' => true,
        'reset'    => false,
        'verify'   => false
    ]);

    // ログイン認証後
    Route::middleware('auth:user')->group(function () {
        // TOPページ
        Route::resource('home', 'HomeController', ['only' => 'index']);
    });
});
// Admin 
Route::namespace('Admin')->prefix('admin')->name('admin.')->group(function () {

    // ログイン認証関連
    Auth::routes([
        'register' => true,
        'reset'    => false,
        'verify'   => false
    ]);

    // ログイン認証後
    Route::middleware('auth:admin')->group(function () {
        // TOPページ
        Route::resource('home', 'HomeController', ['only' => 'index']);
    });
});
```

- `Route::namespace`: 名前空間下のコントローラを表す。`App\Http\Controllers\Admin`等。同じコントローラー名でも見やすかったり、ディレクトリに分けてルートが書ける
- `name`: 名前付きルート。特定のルートへのURLを生成する。
- `prefix`: ルートプレフィックス。グループ内の各ルートに対して、指定されたURIのプレフィックスを指定する。`admin/register`等。
- `only`: 必要なリソースを限定する。上記の場合、`HomeController`はindexしかいらない。

**$redirectToの設定**

- $redirectToのプロパティは`RouteServiceProvider`の定数で管理する。
- 従来、認証関連のリダイレクトは、認証関連のコントローラーの`RedirectTo`プロパティで管理していたが、Ver6.8からRouteServiceProviderの定数HOMEに集約された。
- 具体的には、`app/Providers/RouteServiceProvider.php`で以下のように、それぞれのリダイレクト先を設定する。
```php:
    // Userのリダイレクト先
    public const HOME = '/user/home';
    // Adminのリダイレクト先
    public const ADMIN_HOME = '/admin/home'; 
```
- 未ログイン時の挙動を設定する必要があるので、`app/Http/Middleware/Authenticate.php`に、未ログイン時にログイン認証が必要なページにアクセスした時のリダイレクト先を指定する。
```php:
namespace App\Http\Middleware;

use Illuminate\Support\Facades\Route;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected $user_route  = 'user.login';
    protected $admin_route = 'admin.login';

    protected function redirectTo($request)
    {
        // ルーティングに応じて未ログイン時のリダイレクト先を振り分ける
        if (!$request->expectsJson()) {
            if (Route::is('user.*')) {
                return route($this->user_route);
            } elseif (Route::is('admin.*')) {
                return route($this->admin_route);
            }
        }
    }
}
```
- また、にログインしてる時に`/login`にアクセスしてきた時のリダイレクト先を`app/Http/Middleware/RedirectIfAuthenticated.php`で指定する。
```php:
<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check() && $guard === 'user') {
            return redirect(RouteServiceProvider::HOME);
        } elseif (Auth::guard($guard)->check() && $guard === 'admin') {
            return redirect(RouteServiceProvider::ADMIN_HOME);
        }

        return $next($request);
    }
}
```

**User,AdminのLoginコントローラおよび新規登録コントローラを作成する**

- 標準の`app/Http/Controllers/Auth/LoginController.php`を参考に、下記2種類のLoginControllerを作成する。
  - `app/Http/Controllers/User/Auth/LoginController.php`
  - `app/Http/Controllers/Admin/Auth/LoginController.php`


</details>


