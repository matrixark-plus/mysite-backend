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

namespace App\Controller;

use App\Traits\LogTrait;

class IndexController extends AbstractController
{
    use LogTrait;

    public function __construct()
    {
        // Logger通过LogTrait获取，不需要在此注入
    }

    public function index()
    {
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();

        // 使用LogTrait记录日志
        $this->logAction('IndexController accessed', [
            'method' => $method,
            'user' => $user,
            'timestamp' => time(),
        ], 'app');

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
        ];
    }
}
