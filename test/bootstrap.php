<?php

declare(strict_types=1);

ini_set('display_errors', 'on');
ini_set('display_startup_errors', 'on');

error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

! defined('BASE_PATH') && define('BASE_PATH', dirname(__DIR__, 1));

require BASE_PATH . '/vendor/autoload.php';

// 初始化Hyperf容器
$container = require BASE_PATH . '/config/container.php';

// 设置容器到ApplicationContext
\Hyperf\Context\ApplicationContext::setContainer($container);