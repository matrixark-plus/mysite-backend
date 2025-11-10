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
use App\Event\DataUpdatedEvent;
use App\Event\NewCommentEvent;
use App\Listener\DataUpdatedListener;
use App\Listener\NewCommentListener;
use Hyperf\Command\Listener\FailToHandleListener;
use Hyperf\ExceptionHandler\Listener\ErrorExceptionHandler;

/*
 * This file is part of Hyperf.
 *
 * @see     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    ErrorExceptionHandler::class,
    FailToHandleListener::class,

    // 新评论事件监听器
    [
        'event' => NewCommentEvent::class,
        'listener' => NewCommentListener::class,
    ],
    // 数据更新事件监听器
    [
        'event' => DataUpdatedEvent::class,
        'listener' => DataUpdatedListener::class,
    ],
];
