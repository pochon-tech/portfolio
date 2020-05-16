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
- 標準の`app/Http/Controllers/Auth/RegisterController.php`を参考に、下記2種類のLoginControllerを作成する。
  - `app/Http/Controllers/User/Auth/RegisterController.php`
  - `app/Http/Controllers/Admin/Auth/RegisterController.php`

**View側を作成する**

- `resources/views/layouts/user/app.blade.php`にユーザ画面用のレイアウトを実装する。
- `resources/views/layouts/admin/app.blade.php`に管理画面用のレイアウトを実装する。

- レイアウトを実装したら、ログイン画面を作成する。
- ユーザ用のログイン画面は、`resources/views/user/auth/login.blade.php`とする。
- 管理者用のログイン画面は、`resources/views/admin/auth/login.blade.php`とする。
- ユーザ用の新規登録画面は、`resources/views/user/auth/register.blade.php`とする。
- 管理者用の新規登録画面は、`resources/views/admin/auth/register.blade.php`とする。
- ユーザ用のログイン後の画面は、`resources/views/user/home.blade.php`とする。
- 管理者用のログイン後の画面は、`resources/views/admin/home.blade.php`とする。

- CSSがあたってないと見栄えが悪いので、`laravel/ui`のものを採用する。
```sh:
# laravel/uiのインストール Laravel7.xがリリースされて以降、バージョンを付与しないとエラーになっている。
# Laravel 6.xの場合は、 laravel/ui 1.* Laravel7.xの場合は、 laravel/ui
$ composer require laravel/ui 1.* --dev
# ログイン画面の作成
$ php artisan ui vue --auth
# 上記を実行すると以下のようなファイルが追加・変更が行われる。 
#   backend/laravel/webpack.mix.jsの変更。内容的には変わってない。
#   backend/laravel/resources/js/app.jsの変更。 Vueの読み込みとか
#   backend/laravel/resources/js/bootstrap.jsの変更。 popper.jsの読み込みやjqueryの登録とか。
#   backend/laravel/resources/sass/app.scssの変更。variablesの読み込み、Font読み込みなどなど。
#   backend/laravel/routes/web.phpの変更。認証(Auth::routes();)やHomeへのルーティングが追加されている。
#   backend/laravel/app/Http/Controllers/HomeController.phpの新規追加。
#   backend/laravel/resources/js/components/の新規追加
#   backend/laravel/resources/sass/_variables.scss
#   backend/laravel/resources/views/auth/の新規追加
#   backend/laravel/resources/views/home.blade.phpの新規追加
#   backend/laravel/resources/views/layouts/app.blade.phpの新規追加
# ログイン用テーブルの作成 (序盤で行ったのでやらないでよい。)
# $ php artisan migrate
# Node.jsのインストール
$ curl -sL https://deb.nodesource.com/setup_10.x | bash -
$ apt-get install -y nodejs
# 必要なPackageをインストール
$ npm install
# CSS/JSを作成ビルド
$ npm run dev
```
- 上記を実行することで、public配下にコンパイルされたJSとCSSがコンパイルされる。
- おそらく画面レイアウトが綺麗になっているかと思うので、事前に用意したダミーデータでログインを試す。
</details>

<details><summary>プロジェクトをローカルに展開する(Windows 10 の場合)</summary>

```sh:
# プロジェクトをクローンする。
git clone https://github.com/pochon-tech/portfolio.git .
# ローカル環境にコンテナを立ち上げる。
docker-compose up -d
# Laravelのコンテナに接続する。
docker-compose exec backend bash
# vendorディレクトリが無いので、下記のコマンドを実行して作成する。※注意
# cd laravel; composer update
# composer.lockがある場合は下記の方がよい。
# 下記のコマンドだと、composer.jsonではなく、composer.lockファイルを見にいくため、ライブラリ群のバージョンを他のメンバーと統一することができる。
cd laravel
composer install
# ENVファイルを作成する。MYSQLの接続情報等を書き換える。
cp .env.example .env
vi .env
# アプリケーションキーの初期化をおこなう。これを行うと、ユーザーのセッション情報、パスワードの暗号化をよりセキュアにできる。
php artisan key:generate
# マイグレーションを行う
php artisan migrate
# テストデータを準備する。(Seederがある場合)
php artisan db:seed
# もし、[ReflectionException]とかClass ‘HogeHoge’ not foundのようなエラーが出たら、次のコマンドでオートロードの定義を更新
# composer dump-autoload
# 下記のコマンドで、「再マイグレーション＆seed実行」が可能。マイグレーションファイル再定義したときとかに覚えておくと便利。
# php artisan migrate:refresh --seed
# Storageディレクトリを書き込めるようにしておく。
chmod -R 777 storage
# publicディレクトリの参照を設定する。
rm -rf /var/www/html/
ln -s /var/www/laravel/public/ /var/www/html
```

</details>

<details><summary>問い合わせページの作成</summary>

- 前述の手順でローカル環境にプロジェクトをClone。(既存であるなら問題ない)
- 基本的なCRUDを実装する。

**モデルの作成**

```sh:
$ docker-compose run backend bash -c "cd laravel; php artisan make:model Contact --migration"
```
- 上記のコマンドを実行することで、Contactモデルとマイグレーションファイルが自動生成される。
- 作成されたマイグレーションファイルを開き、upメソッドを更新する。

```php:backend\laravel\database\migrations\2020_05_16_034540_create_contacts_table.php
Schema::create('contacts', function (Blueprint $table) {
    $table->increments('id');
    $table->timestamps();
    $table->string('first_name');
    $table->string('last_name');
    $table->string('email');
    $table->string('job_title');
    $table->string('city');   
    $table->string('country');
});
```
- Schemaファサードのcreateメソッドを使用してテーブルを作成することができる。
- createメソッドは引数を2つ受け取る。最初は「テーブル名」で、2つ目は新しいテーブルを定義するために使用する「Blueprint」オブジェクトを受け取るクロージャ。
- 上記の変更が終わったら、下記コマンドで、テーブルの作成を実行する。

```sh:
$ docker-compose run backend bash -c "cd laravel; php artisan migrate"
# Mysqlに接続
$ docker-compose exec mysql bash -c "mysql -uuser -ppassword -Dlaravel"
# どのようなテーブルが作られたか確認
> SHOW CREATE TABLE `contacts`\G;
```
- 続いて、モデルを編集する。
- 生成されたContact.phpをmodelsディレクトリに移動し、下記の変更を行う。

```php:
namespace App\Models; // modelsディレクトリに移動させたので
class Contact extends Model
{
    // ホワイトリスト： $fillableに指定したカラムのみ、create()やfill()、update()で値が代入される。
    // $contact->update($request->all()); <- $fillableに指定していないもの以外は入らない。
    protected $fillable = [
        'first_name', 'last_name', 'email', 'city', 'country', 'job_title'
    ];
    // ブラックリスト：$guardedに指定したカラムのみ、create()やfill()、update()で値が代入されない。
    // $contact->update($request->all()); <- $guardedに指定していないものは全て入り得る
    // protected $guarded = [];
}
```
- $fillableと$guardedは、**Model・DB単位で予期せぬ代入が起こると困るもの**を書く。どちらか一方で構わない。
- $fillableを採用する
  - $fillable を採用する理由として、**Eloquentからの派生Classの$fillableの記述を見るだけで、そのClassが持ちうるプロパティが一目でわかりやすい**。※ただし、Relationは確認できない。
  - $guarded を採用する場合、DBを眺めてClassのプロパティについて調べるコストが発生する。
  - 2〜10個のフィールドがある場合は、fillableを使用するのが適切。それ以上は多くて見づらい。

**コントローラおよびルーティングの作成**

- モデルを作成した後は、下記のコマンドでコントローラを作成する。

```sh:
$ docker-compose run backend bash -c "cd laravel; php artisan make:controller ContactController --resource"
```
- 次に`routes/web.php`ファイルを開き、ルーティングを追加する。

```php:
// Contact系
Route::resource('contacts', 'ContactController');
```
- 上記の一行で、各メソッドに紐づいたルーティングが定義される。
- 紐づいたルーティングの確認は、下記のコマンドを実行することで確認することができる。

```sh:
$ docker-compose run backend bash -c "cd laravel; php artisan route:list"
+--------+-----------+-------------------------------+-----------------------+-------------------------------------------------------------------------+------------------------------------------------------+
| Domain | Method    | URI                           | Name                  | Action                                                                  | Middleware                                           |
+--------+-----------+-------------------------------+-----------------------+-------------------------------------------------------------------------+------------------------------------------------------+
|        | GET|HEAD  | contacts                      | contacts.index        | App\Http\Controllers\ContactController@index                            | web                                                  |
|        | POST      | contacts                      | contacts.store        | App\Http\Controllers\ContactController@store                            | web                                                  |
|        | GET|HEAD  | contacts/create               | contacts.create       | App\Http\Controllers\ContactController@create                           | web                                                  |
|        | GET|HEAD  | contacts/{contact}            | contacts.show         | App\Http\Controllers\ContactController@show                             | web                                                  |
|        | PUT|PATCH | contacts/{contact}            | contacts.update       | App\Http\Controllers\ContactController@update                           | web                                                  |
|        | DELETE    | contacts/{contact}            | contacts.destroy      | App\Http\Controllers\ContactController@destroy                          | web                                                  |
|        | GET|HEAD  | contacts/{contact}/edit       | contacts.edit         | App\Http\Controllers\ContactController@edit                             | web   
```
- ちなみに、RESTful APIのみを公開するコントローラーを作成する場合は、`Route::apiResource('contacts', 'ContactController');`のように、ルーティングに定義することで、HTMLテンプレートの提供に使用されるルートを除外できる。

**CRUD操作の実装**

- まずは、作成されたContactコントローラ内でContactモデルを使用するために、`use`する。

```php:
use App\Models\Contact;
```
- 続いて、`store()メソッド`内で登録処理を実装する。

```php:
    public function store(Request $request)
    {
        // 入力項目のValidate
        $request->validate([
            'first_name'=>'required',
            'last_name'=>'required',
            'email'=>'required'
        ]);
        // モデルインスタンスに値を格納
        $contact = new Contact([
            'first_name' => $request->get('first_name'),
            'last_name' => $request->get('last_name'),
            'email' => $request->get('email'),
            'job_title' => $request->get('job_title'),
            'city' => $request->get('city'),
            'country' => $request->get('country')
        ]);
        // DBへ登録
        $contact->save();
        return redirect('/contacts')->with('success', 'Contact saved!');
    }
```
- 続いて、`create()メソッド`に描画するテンプレートを追加する。

```php:
    public function create()
    {
        return view('contacts.create');
    }
```
- ここで、`create()メソッド`では、使用可能なテンプレート`create.blade.php`が`resources/views/contacts`フォルダ内に存在する必要がある。
- なので、`contacts/create.blade.php`を作成する。

```sh:
$ mkdir backend/laravel/resources/views/contacts
$ touch backend/laravel/resources/views/contacts/create.blade.php
```
- 今回、User側のレイアウトを想定して、テンプレートを実装する。

```php:
@extends('layouts.user.app')

@section('content')
<div class="row">
 <div class="col-sm-8 offset-sm-2">
    <h1 class="display-6">お問い合わせ</h1>
  <div>
    @if ($errors->any())
      <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
        </ul>
      </div><br />
    @endif
      <form method="post" action="{{ route('contacts.store') }}">
          @csrf
          <div class="form-group">    
              <label for="first_name">First Name:</label>
              <input type="text" class="form-control" name="first_name"/>
          </div>

          <div class="form-group">
              <label for="last_name">Last Name:</label>
              <input type="text" class="form-control" name="last_name"/>
          </div>

          <div class="form-group">
              <label for="email">Email:</label>
              <input type="text" class="form-control" name="email"/>
          </div>
          <div class="form-group">
              <label for="city">City:</label>
              <input type="text" class="form-control" name="city"/>
          </div>
          <div class="form-group">
              <label for="country">Country:</label>
              <input type="text" class="form-control" name="country"/>
          </div>
          <div class="form-group">
              <label for="job_title">Job Title:</label>
              <input type="text" class="form-control" name="job_title"/>
          </div>                         
          <button type="submit" class="btn btn-primary">Add contact</button>
      </form>
  </div>
</div>
</div>
@endsection
```
- 続いて、`index()メソッド`内で一覧取得処理を実装する。

```php:
    public function index()
    {
        $contacts = Contact::all();
        return view('contacts.index', compact('contacts'));
    }
```
- 登録の時と同じように、対応するテンプレートを作成する。

```sh:
$ touch backend/laravel/resources/views/contacts/index.blade.php
```
- 一覧の中身を実装する。

```php:
@extends('layouts.user.app')

@section('content')
<div class="row">
<div class="col-sm-12">
    <h1 class="display-6">お問い合わせ</h1>  
  <table class="table table-striped">
    <thead>
        <tr>
          <td>ID</td>
          <td>Name</td>
          <td>Email</td>
          <td>Job Title</td>
          <td>City</td>
          <td>Country</td>
          <td colspan = 2>Actions</td>
        </tr>
    </thead>
    <tbody>
        @foreach($contacts as $contact)
        <tr>
            <td>{{$contact->id}}</td>
            <td>{{$contact->first_name}} {{$contact->last_name}}</td>
            <td>{{$contact->email}}</td>
            <td>{{$contact->job_title}}</td>
            <td>{{$contact->city}}</td>
            <td>{{$contact->country}}</td>
            <td>
                <a href="{{ route('contacts.edit',$contact->id) }}" class="btn btn-primary">Edit</a>
            </td>
            <td>
                <form action="{{ route('contacts.destroy', $contact->id) }}" method="post">
                  @csrf
                  @method('DELETE')
                  <button class="btn btn-danger" type="submit">Delete</button>
                </form>
            </td>
        </tr>
        @endforeach
    </tbody>
  </table>
<div>
</div>
@endsection
```
- 続いて、`edit()メソッド`に更新対象の情報を取得して更新画面描画処理を実装する。

```php:
    public function edit($id)
    {
        $contact = Contact::find($id);
        return view('contacts.edit', compact('contact'));        
    }
```
- 続いて、`update()メソッド`に実際の更新処理を実装する。

```php:
    public function update(Request $request, $id)
    {
        $request->validate([
            'first_name'=>'required',
            'last_name'=>'required',
            'email'=>'required'
        ]);

        $contact = Contact::find($id);
        $contact->first_name =  $request->get('first_name');
        $contact->last_name = $request->get('last_name');
        $contact->email = $request->get('email');
        $contact->job_title = $request->get('job_title');
        $contact->city = $request->get('city');
        $contact->country = $request->get('country');
        $contact->save();

        return redirect('/contacts')->with('success', 'Contact updated!');
    }
```
- 登録の時と同じように、対応するテンプレートを作成する。

```sh:
$ touch backend/laravel/resources/views/contacts/edit.blade.php
```
- 更新用画面を実装する。

```php:
@extends('layouts.user.app')

@section('content')
<div class="row">
    <div class="col-sm-8 offset-sm-2">
        <h1 class="display-3">Update a contact</h1>

        @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        <br /> 
        @endif
        <form method="post" action="{{ route('contacts.update', $contact->id) }}">
            @method('PATCH') 
            @csrf
            <div class="form-group">

                <label for="first_name">First Name:</label>
                <input type="text" class="form-control" name="first_name" value={{ $contact->first_name }} />
            </div>

            <div class="form-group">
                <label for="last_name">Last Name:</label>
                <input type="text" class="form-control" name="last_name" value={{ $contact->last_name }} />
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="text" class="form-control" name="email" value={{ $contact->email }} />
            </div>
            <div class="form-group">
                <label for="city">City:</label>
                <input type="text" class="form-control" name="city" value={{ $contact->city }} />
            </div>
            <div class="form-group">
                <label for="country">Country:</label>
                <input type="text" class="form-control" name="country" value={{ $contact->country }} />
            </div>
            <div class="form-group">
                <label for="job_title">Job Title:</label>
                <input type="text" class="form-control" name="job_title" value={{ $contact->job_title }} />
            </div>
            <button type="submit" class="btn btn-primary">Update</button>
        </form>
    </div>
</div>
@endsection
```
- 続いて、`destroy()`メソッドに削除処理を実装する。

```php:
    public function destroy($id)
    {
        $contact = Contact::find($id);
        $contact->delete();

        return redirect('/contacts')->with('success', 'Contact deleted!');
    }
```
- 今まで、登録・更新・削除処理を実装する中で、noticeを一覧画面へ返すような処理を実装していたので、一覧画面(index.blade.php)でnoticeが表示されうように修正する。

```php:
@section('content')
<div class="col-sm-12">
  @if(session()->get('success'))
    <div class="alert alert-success">
      {{ session()->get('success') }}  
    </div>
  @endif
</div>
```
- 以上で、BaseなCRUD操作の実装が完了。
- 良いタイミングなので、Git Tag付けしておく。

```sh:
# 今までの作業内容をコミットPUSH
$ git add -A; git commit -m"proceeded"; git push origin;
# ローカルのタグを消す
$ git tag -d v1.0
# リモートのタグ消す場合
$ git push origin :v1.0
# タグを付ける
$ git tag -a v1.0 -m 'Base CRUD Application & Multi Auth'
# リモートにタグ反映
$ git push origin v1.0
# リモートにタグ全て反映
#$ git push origin --tags
# タグベースでClone 
$ git clone リポジトリ名 -b ブランチorタグ名
```

**画像アップロード機能追加**

- お問い合わせ画面に画像のアップロード機能を追加する。
- アップロード画像のfilename格納するカラムを追加する必要があるので、マイグレーションファイルを新たに作成する。
- マイグレーションのファイル名は以下を参考に決めた。
  - カラム名はファイル名に含めない。複数カラムの場合や開発途中で名称変更となった場合手間。
  - 日付でユニーク化。
  - _tableは省略。テーブル操作なので。

```sh:
$ docker-compose run backend bash -c "cd laravel; php artisan make:migration modifycontacts_20200516 --table=contacts"
```
- 作成したら、下記のようにカラム追加・削除の定義を行う。

```php:
    public function up()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('file_name');
        });
    }
    public function down()
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('file_name');
        });
    }
```
- 追記したら、マイグレーションを実行する。

```sh:
$ docker-compose run backend bash -c "cd laravel; php artisan migrate"
```
- 続いて、テンプレートにfile選択を追加する。Formのenctypeも変更。

```php:
      <form method="post" action="{{ route('contacts.store') }}" enctype="multipart/form-data">
          @csrf
          <div class="form-group">
              <label for="file">file:</label>
              <input type="file" class="form-control" name="file">
          </div>
```
- 続いて、コントローラの`store()メソッド`に処理を追加する。

```php:

    public function store(Request $request)
    {
        // 入力項目のValidate
        $request->validate([
            'file' => 'required|file|image|mimes:jpeg,png,jpg,gif|max:2048',
```
- Validateで指定した夫々の機能について
  - file: フィールドがアップロードに成功したファイルであることをバリデートする。
  - image: フィールドで指定されたファイルが画像(jpg、png、bmp、gif、svg)であることをバリデートする。
  - mimes:foo,bar,…: フィールドで指定されたファイルが拡張子のリストの中のMIMEタイプのどれかと一致することをバリデートする。
  - max: フィールドが最大値として指定された値以下であることをバリデートする。sizeルールと同様の判定方法で、文字列、数値、配列、ファイルが評価される。
- 続いて、`store()メソッド`に画像を保存する処理を追加する。
- 今回はローカルの`storage/images`ディレクトリ配下に画像は保存する。
```php:
    public function store(Request $request)
    {
        $filename = $request->file->store('public/images');
        // モデルインスタンスに値を格納
        $contact = new Contact([
            'file_name' => basename($filename),
```
- また、使用しているContactモデルには、ホワイトリストに「file_name」を事前に追加しておく。
- 続いて、画像の表示を実装する。
- Storage配下に画像を保存しているので、シンボリックリンクを張る必要がある。

```php:
$ docker-compose run backend bash -c "cd laravel; php artisan storage:link"
```
- 上記を実行することで、public/storage から storage/public にシンボリックリンクが作成される。
- 一覧画面に画像を表示するようにテンプレートを修正する。

```php:
    <td><img src="{{asset('storage/images/'.$contact->file_name)}}" width="100px" height="100px"></td>
```
- 続いて、更新の画面も修正しておく。

```php:
        <form method="post" action="{{ route('contacts.update', $contact->id) }}" enctype="multipart/form-data">
            @method('PATCH') 
            @csrf
            <div class="form-group">
                <label for="first_name">Image:</label>
                <input type="file" class="form-control" name="file">
            </div>
```

</details>
