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
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;

/**
 * @Controller(prefix="/index")
 */
class IndexController extends AbstractController
{
    use LogTrait;

    /**
     * 首页入口.
     * @RequestMapping(path="", methods={"GET"})
     */
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

        return $this->success([
            'method' => $method,
            'message' => "Hello {$user}.",
        ], '请求成功');
    }
}
