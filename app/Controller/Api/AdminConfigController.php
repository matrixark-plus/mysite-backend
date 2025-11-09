<?php

declare(strict_types=1);
/**
 * 配置管理控制器
 * 提供系统配置的获取和更新API
 */

namespace App\Controller\Api;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Service\EnvironmentFileService;
use App\Service\SystemConfigService;
use App\Traits\LogTrait;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Annotation\Middleware;
use App\Middleware\JwtAuthMiddleware;

/**
 * @Controller(prefix="/api/admin/config")
 * @Middleware({JwtAuthMiddleware::class, "admin"})
 */
class AdminConfigController extends AbstractController
{
    use LogTrait;

    /**
     * @var SystemConfigService
     * @Inject
     */
    protected $systemConfigService;

    /**
     * @var EnvironmentFileService
     * @Inject
     */
    protected $environmentFileService;

    /**
     * 获取单个配置
     * @RequestMapping(path="get", methods={"GET"})
     */
    public function getConfig()
    {
        try {
            $key = $this->request->input('key');
            if (empty($key)) {
                return $this->fail(StatusCode::BAD_REQUEST, '配置键不能为空');
            }

            $value = $this->systemConfigService->getConfig($key);
            
            return $this->success([
                'key' => $key,
                'value' => $value
            ], '获取配置成功');
        } catch (\Exception $e) {
            $this->logError('获取配置失败: ' . $e->getMessage());
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 获取所有配置
     * @RequestMapping(path="all", methods={"GET"})
     */
    public function getAllConfigs()
    {
        try {
            $configs = $this->systemConfigService->getAllConfigs();
            
            return $this->success($configs, '获取所有配置成功');
        } catch (\Exception $e) {
            $this->logError('获取所有配置失败: ' . $e->getMessage());
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取所有配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新单个配置
     * @RequestMapping(path="update", methods={"POST"})
     */
    public function updateConfig()
    {
        try {
            $key = $this->request->input('key');
            $value = $this->request->input('value');
            
            if (empty($key)) {
                return $this->fail(StatusCode::BAD_REQUEST, '配置键不能为空');
            }

            $result = $this->systemConfigService->setConfig($key, $value);
            
            if ($result) {
                return $this->success(null, '更新配置成功');
            } else {
                return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '更新配置失败');
            }
        } catch (\Exception $e) {
            $this->logError('更新配置失败: ' . $e->getMessage());
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '更新配置失败: ' . $e->getMessage());
        }
    }

    /**
     * 批量更新配置
     * @RequestMapping(path="batch-update", methods={"POST"})
     */
    public function batchUpdateConfig()
    {
        try {
            $configs = $this->request->input('configs', []);
            
            if (!is_array($configs) || empty($configs)) {
                return $this->fail(StatusCode::BAD_REQUEST, '配置数据格式错误');
            }

            $result = $this->systemConfigService->setConfigs($configs);
            
            if ($result) {
                return $this->success(null, '批量更新配置成功');
            } else {
                return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '批量更新配置失败');
            }
        } catch (\Exception $e) {
            $this->logError('批量更新配置失败: ' . $e->getMessage());
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '批量更新配置失败: ' . $e->getMessage());
        }
    }
}