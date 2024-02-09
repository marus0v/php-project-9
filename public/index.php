<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Подключение сторонних библиотек
use Slim\Factory\AppFactory;
use DI\Container;
// use App\Validator;
use App\PostgreSQLTutorial\Connection;

// СТАРТ СЕССИИ
session_start();

// ПОДКЛЮЧЕНИЕ КОНТЕЙНЕРОВ
$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

AppFactory::setContainer($container);
$app = AppFactory::create();

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new Slim\Flash\Messages();
});

// Подключение базы
$databaseUrl = parse_url($_ENV['DATABASE_URL']);
$username = $databaseUrl['user']; // marus
$password = $databaseUrl['pass']; // s1ckmyduck
$host = $databaseUrl['host']; // localhost
$port = $databaseUrl['port']; // 5432
$dbName = ltrim($databaseUrl['path'], '/');
$dbNameLocal = 'marus_pa';

/*try {
    Connection::get()->connect();
    echo 'A connection to the PostgreSQL database sever has been established successfully.';
} catch (\PDOException $e) {
    echo $e->getMessage();
} */

$app = AppFactory::createFromContainer($container);

$app->addErrorMiddleware(true, true, true);

$router = $app->getRouteCollector()->getRouteParser();

// ДОМАШНЯЯ СТРАНИЦА
$app->get('/', function ($request, $response) use ($router) {
    // $router->urlFor('users');
    // $router->urlFor('user/new');
    // return $response->write('Welcome to Page-Analizer!');
    $params = [
        'welcome' => 'Welcome to Page-Analizer!',
        ];
        return $this->get('renderer')->render($response, 'index.phtml', $params);
        //            ->get('flash')->addMessage('success', 'This is a message');
    })->setName('/');

$app->run();