<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Подключение сторонних библиотек
use Slim\Factory\AppFactory;
use DI\Container;
// use App\Validator;
use App\PostgreSQLTutorial\Connection;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

// СТАРТ СЕССИИ
session_start();

// ПОДКЛЮЧЕНИЕ КОНТЕЙНЕРОВ
$container = new Container();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->add(function (Request $request, RequestHandler $handler) use ($container) {
    $routeContext = RouteContext::fromRequest($request);
    $route = $routeContext->getRoute();
    $routeName = !empty($route) ? $route->getName() : '';
    $container->set('routeName', $routeName);
    return $handler->handle($request);
});

$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);

$container->set('router', $app->getRouteCollector()->getRouteParser());

$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

// Подключение базы
$container->set('connection', function () {
    $conn = new App\Connection();
    return $conn->connect();
});
/*
$databaseUrl = parse_url($_ENV['DATABASE_URL']);
$username = $databaseUrl['user']; // marus
$password = $databaseUrl['pass']; // s1ckmyduck
$host = $databaseUrl['host']; // localhost
$port = $databaseUrl['port']; // 5432
$dbName = ltrim($databaseUrl['path'], '/');
$dbNameLocal = 'marus_pa'; */

/*try {
    Connection::get()->connect();
    echo 'A connection to the PostgreSQL database sever has been established successfully.';
} catch (\PDOException $e) {
    echo $e->getMessage();
} */

$container->set('renderer', function () use ($container) {
    $templateVars = [
        'routeName' => $container->get('routeName'),
        'router' => $container->get('router'),
        'flash' => $container->get('flash')->getMessages()
    ];
    $renderer = new \Slim\Views\PhpRenderer(__DIR__ . '/../templates', $templateVars);
    $renderer->setLayout('layout.phtml');
    return $renderer;
});

// ДОМАШНЯЯ СТРАНИЦА
/* $app->get('/', function ($request, $response) use ($router) {
    // $router->urlFor('users');
    // $router->urlFor('user/new');
    // return $response->write('Welcome to Page-Analizer!');
    $params = [
        'welcome' => 'Welcome to Page-Analizer!',
        ];
        return $this->get('renderer')->render($response, 'index.phtml', $params);
        //            ->get('flash')->addMessage('success', 'This is a message');
    })->setName('/'); */
$app->get('/', function ($request, $response) {
        return $this->get('renderer')->render($response, 'home.phtml');
    })->setName('home');

$app->get('/urls', function ($request, $response) {
    $allUrls = $this->get('connection')
        ->query('SELECT id, name FROM urls ORDER BY id DESC')
        ->fetchAll(PDO::FETCH_ASSOC);

    $lastChecks = $this->get('connection')
        ->query('SELECT
                url_id, 
                MAX(created_at) AS last_check_created_at,
                status_code 
            FROM url_checks 
            GROUP BY url_id, status_code')
        ->fetchAll(PDO::FETCH_ASSOC);

    $urls = collect((array) $allUrls);
    $urlChecks = collect((array) $lastChecks)->keyBy('url_id');

    $data = $urls->map(function ($url) use ($urlChecks) {
        $urlCheck = $urlChecks->firstWhere('url_id', $url['id']);

        return [
            'id' => $url['id'],
            'name' => $url['name'],
            'last_check_created_at' => $urlCheck['last_check_created_at'] ?? null,
            'status_code' => $urlCheck['status_code'] ?? null,
        ];
    });

    $params = ['data' => $data];
    return $this->get('renderer')->render($response, 'urls/index.phtml', $params);
})->setName('urls.index');

$app->get('/urls/{id:[0-9]+}', function ($request, $response, $args) {
    $id = $args['id'];

    $urlDataQuery = 'SELECT * FROM urls WHERE id = :id';
    $urlDataStmt = $this->get('connection')->prepare($urlDataQuery);
    $urlDataStmt->execute([':id' => $id]);
    $urlData = $urlDataStmt->fetch();

    if (empty($urlData)) {
        return $this->get('renderer')->render($response->withStatus(404), 'error404.phtml');
    }

    $urlChecksQuery = 'SELECT * FROM url_checks WHERE url_id = :id ORDER BY id DESC';
    $urlChecksStmt = $this->get('connection')->prepare($urlChecksQuery);
    $urlChecksStmt->execute([':id' => $id]);
    $urlChecksData = $urlChecksStmt->fetchAll();

    $params = [
        'url' => $urlData,
        'urlChecks' => $urlChecksData
    ];
    return $this->get('renderer')->render($response, 'urls/show.phtml', $params);
})->setName('urls.show');

$app->post('/urls', function ($request, $response) {
    $url = $request->getParsedBodyParam('url');

    $v = new Validator(['url_name' => $url['name']]);
    $v->rule('required', 'url_name')->message('URL не должен быть пустым');
    $v->rule('lengthMax', 'url_name', 255)->message('URL превышает 255 символов');
    $v->rule('url', 'url_name')->message('Некорректный URL');

    if (!$v->validate()) {
        $params = [
            'url' => $url['name'],
            'errors' => $v->errors()
        ];
        return $this->get('renderer')->render($response->withStatus(422), 'home.phtml', $params);
    }

    $parsedUrl = parse_url(Str::lower($url['name']));
    $urlName = "{$parsedUrl['scheme']}://{$parsedUrl['host']}";

    $urlIdQuery = 'SELECT id FROM urls WHERE name = :name';
    $urlIdStmt = $this->get('connection')->prepare($urlIdQuery);
    $urlIdStmt->execute([':name' => $urlName]);
    $urlId = $urlIdStmt->fetch();

    if (!empty($urlId)) {
        $this->get('flash')->addMessage('success', 'Страница уже существует');
        return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $urlId['id']]));
    }

    $newUrlQuery = 'INSERT INTO urls(name, created_at) VALUES(:name, :created_at)';
    $newUrlStmt = $this->get('connection')->prepare($newUrlQuery);
    $newUrlStmt->execute([':name' => $urlName, ':created_at' => Carbon::now()]);

    $lastInsertId = $this->get('connection')->lastInsertId();
    $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $lastInsertId]));
})->setName('urls.store');

$app->post('/urls/{url_id:[0-9]+}/checks', function ($request, $response, $args) {
    $urlId = $args['url_id'];

    $urlNameQuery = 'SELECT name FROM urls WHERE id = :id';
    $urlNameStmt = $this->get('connection')->prepare($urlNameQuery);
    $urlNameStmt->execute([':id' => $urlId]);
    $urlName = $urlNameStmt->fetch();

    $client = new Client();

    try {
        $res = $client->request('GET', $urlName['name'], ['allow_redirects' => false]);
        $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    } catch (ClientException $e) {
        $res = $e->getResponse();
        $this->get('flash')->addMessage('warning', 'Проверка была выполнена успешно, но сервер ответил c ошибкой');
        return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $urlId]));
    } catch (ServerException $e) {
        $res = $e->getResponse();
        $this->get('flash')->addMessage('warning', 'Проверка была выполнена успешно, но сервер ответил c ошибкой');
        return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $urlId]));
    } catch (ConnectException $e) {
        $this->get('flash')->addMessage('error', 'Произошла ошибка при проверке, не удалось подключиться');
        return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $urlId]));
    } catch (TransferException $e) {
        $this->get('flash')->addMessage('error', 'Упс что-то пошло не так');
        return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $urlId]));
    }

    $statusCode = $res->getStatusCode();
    $resBody = (string) $res->getBody();

    if (!empty($resBody)) {
        $document = new Document($resBody);
        $h1 = Str::limit(optional($document->first('h1'))->text() ?? '', 250, '(...)');
        $title = Str::limit(optional($document->first('title'))->text() ?? '', 250, '(...)');
        $description = optional($document->first('meta[name="description"]'))
                ->getAttribute('content') ?? '';
    }

    $newCheckQuery = 'INSERT INTO url_checks(
                url_id,
                status_code,
                h1,
                title,
                description,
                created_at
        ) VALUES(
                :url_id,
                :status_code,
                :h1,
                :title,
                :description,
                :check_created_at
            )';
    $newCheckStmt = $this->get('connection')->prepare($newCheckQuery);
    $newCheckStmt->execute([
        ':url_id' => $urlId,
        ':status_code' => $statusCode,
        ':h1' => $h1 ?? null,
        ':title' => $title ?? null,
        ':description' => $description ?? null,
        ':check_created_at' => Carbon::now()
    ]);

    return $response->withRedirect($this->get('router')->urlFor('urls.show', ['id' => $urlId]));
})->setName('urls.checks.store');


$app->run();