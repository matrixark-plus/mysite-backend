<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    /*
     * 支持的IDE类型：
     * "sublime", "textmate", "cursor", "emacs", "macvim", "phpstorm", "idea",
     * "vscode", "vscode-insiders", "vscode-remote", "vscode-insiders-remote",
     * "atom", "nova", "netbeans", "xdebug"
     */
    'ide' => 'DEVTOOL_IDE', 'vscode',
    'generator' => [
        'amqp' => [
            'consumer' => [
                'namespace' => 'App\Amqp\Consumer',
            ],
            'producer' => [
                'namespace' => 'App\Amqp\Producer',
            ],
        ],
        'aspect' => [
            'namespace' => 'App\Aspect',
        ],
        'command' => [
            'namespace' => 'App\Command',
        ],
        'controller' => [
            'namespace' => 'App\Controller',
        ],
        'job' => [
            'namespace' => 'App\Job',
        ],
        'listener' => [
            'namespace' => 'App\Listener',
        ],
        'middleware' => [
            'namespace' => 'App\Middleware',
        ],
        'Process' => [
            'namespace' => 'App\Processes',
        ],
    ],
];
