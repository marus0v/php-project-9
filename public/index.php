<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Подключение сторонних библиотек
use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

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
// });

$app->run();