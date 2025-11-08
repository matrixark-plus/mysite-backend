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

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Middleware\JwtAuthMiddleware;
use App\Service\SystemService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * @Controller(prefix="/api/config")
 * @Middleware({JwtAuthMiddleware::class, "admin"})
 */
class ConfigController extends AbstractController
{
    use LogTrait;

    /**
     * @Inject
     * @var SystemService
     */
    protected $systemService;

    /**
     * 获取配置.
     *
     * @RequestMapping(path="/get", methods={"GET"})
     */
    public function getConfig(RequestInterface $request)
    {
        try {
            $key = $request->input('key');
            $config = $this->systemService->getConfig($key);
            return $this->success($config);
        } catch (Exception $e) {
            $this->logError('获取配置异常', [], $e, 'system');
            return $this->fail(500, '获取配置失败');
        }
    }

    /**
     * 更新配置.
     *
     * @RequestMapping(path="/update", methods={"POST"})
     */
    public function updateConfig(RequestInterface $request)
    {
        try {
            $key = $request->input('key');
            $value = $request->input('value');

            if (! $key) {
                return $this->fail(400, '配置键不能为空');
            }

            $result = $this->systemService->updateConfig($key, $value);

            if ($result) {
                return $this->success(null, '配置更新成功');
            }
            return $this->fail(500, '配置更新失败');
        } catch (Exception $e) {
            $this->logError('更新配置异常', [], $e, 'system');
            return $this->fail(500, '更新配置失败');
        }
    }

    /**
     * 批量更新配置.
     *
     * @RequestMapping(path="/batch-update", methods={"POST"})
     */
    public function batchUpdateConfig(RequestInterface $request)
    {
        try {
            $configs = $request->input('configs');

            if (! is_array($configs) || empty($configs)) {
                return $this->fail(400, '配置数据不能为空');
            }

            $result = $this->systemService->batchUpdateConfig($configs);

            if ($result) {
                return $this->success(null, '配置批量更新成功');
            }
            return $this->fail(500, '配置批量更新失败');
        } catch (Exception $e) {
            $this->logError('批量更新配置异常', [], $e, 'system');
            return $this->fail(500, '批量更新配置失败');
        }
    }
}
