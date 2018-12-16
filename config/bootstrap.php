<?php

use Zend\Session\SessionManager;
use Zend\Session\ManagerInterface;
use Zend\Session\Config\SessionConfig;
use Monolog\ErrorHandler;
use Monolog\Handler\BrowserConsoleHandler;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface as Logger;
use Zend\Config\Config;

$containerBuilder = new \DI\ContainerBuilder;
/* @var \DI\Container $container */
$container = $containerBuilder->build();

$container->set(\GuzzleHttp\Client::class, function() use ($container){
   return new \GuzzleHttp\Client([]);
});

$container->set(\FastRoute\Dispatcher::class, function(){
   $routeList = require APP_PATH.'config/routes.php';

    /** @var \FastRoute\Dispatcher $dispatcher */
    return FastRoute\simpleDispatcher(
        function(\FastRoute\RouteCollector $r) use ($routeList){
            foreach ($routeList as $routeDef){
                $r->addRoute($routeDef[0], $routeDef[1], $routeDef[2]);
            }
        }
    );
});

$container->set(Config::class, function(){
   $params = include(APP_PATH . "/config/config.php");
   $config = new \Zend\Config\Config($params);
   $config = $config->merge(new \Zend\Config\Config(include(APP_PATH . 'config/config-local.php')));

   return $config;
});

$container->set(ManagerInterface::class, function(){
    $sessionConfig = new SessionConfig();
    $sessionConfig->setOptions([
        'remember_me_seconds' => 2592000, //2592000, // 30 * 24 * 60 * 60 = 30 days
        'use_cookies'         => true,
        'cookie_httponly'     => true,
        'name'                => 'skolica',
        'cookie_lifetime'     => 60 * 60 * 2,
    ]);

    $session = new SessionManager($sessionConfig);
    $session->start();

    return $session;
});

$container->set(Logger::class, function(){
    $logger = new \Monolog\Logger('skeletonlog');

    $date = new \DateTime('now', new \DateTimeZone('Europe/Belgrade'));

    $logDir = APP_PATH . '/data/logs' . $date->format('Y') . '-' . $date->format('m');
    $logFile = $logDir . '/' . gethostname() . '-' . $date->format('d') . '.log';
    $debugLog = APP_PATH . '/data/logs/debug.log';

    if(!is_dir($logDir)){
        mkdir($logDir);
    }
    if(!file_exists($logFile)){
        touch($logFile);
    }

    $logger->pushHandler(new StreamHandler($logFile));
    $logger->pushHandler(new StreamHandler($debugLog, \Monolog\Logger::DEBUG));
    $logger->pushHandler(new BrowserConsoleHandler());
    ErrorHandler::register($logger);

    return $logger;
});

$container->set(PDO::class, function() use ($container){
   $config = $container->get(Config::class);
   $dsn = "mysql:host={$config->db->host};dbname={$config->db->name}";
   $options = array(
       PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8',
   );

   return new \PDO($dsn, $config->db->user, $config->db->pass, $options);
});

$container->set(\Redis::class, function() use ($container){
    $dt = new \DateTime('now', new \DateTimeZone($container->get(Config::class)->offsetGet('timezone')));

    return $dt;
});

return $container;



























