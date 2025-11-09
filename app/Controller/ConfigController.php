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

use App\Service\EnvironmentFileService;
use App\Service\SystemConfigService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * 配置管理控制器
 * 提供系统配置的获取和更新API
 */
#[Controller(prefix: '/api/config')]
class ConfigController extends AbstractController
{
    /**
     * @Inject
     * @var SystemConfigService
     */
    protected $systemConfigService;

    /**
     * @Inject
     * @var EnvironmentFileService
     */
    protected $environmentFileService;

    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 获取单个配置
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'get', methods: ['GET'])]
    public function getConfig(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $key = $request->input('key');
            if (empty($key)) {
                return $response->json(['code' => 400, 'message' => '配置键不能为空']);
            }

            $value = $this->systemConfigService->getConfig($key);
            
            return $response->json([
                'code' => 200,
                'message' => '获取配置成功',
                'data' => [
                    'key' => $key,
                    'value' => $value
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取配置失败: ' . $e->getMessage());
            return $response->json(['code' => 500, 'message' => '获取配置失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 获取所有配置
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'all', methods: ['GET'])]
    public function getAllConfigs(ResponseInterface $response)
    {
        try {
            $configs = $this->systemConfigService->getAllConfigs();
            
            return $response->json([
                'code' => 200,
                'message' => '获取所有配置成功',
                'data' => $configs
            ]);
        } catch (\Exception $e) {
            $this->logger->error('获取所有配置失败: ' . $e->getMessage());
            return $response->json(['code' => 500, 'message' => '获取所有配置失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 更新单个配置
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'update', methods: ['POST'])]
    public function updateConfig(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $key = $request->input('key');
            $value = $request->input('value');
            
            if (empty($key)) {
                return $response->json(['code' => 400, 'message' => '配置键不能为空']);
            }

            $result = $this->systemConfigService->setConfig($key, $value);
            
            if ($result) {
                return $response->json(['code' => 200, 'message' => '更新配置成功']);
            } else {
                return $response->json(['code' => 500, 'message' => '更新配置失败']);
            }
        } catch (\Exception $e) {
            $this->logger->error('更新配置失败: ' . $e->getMessage());
            return $response->json(['code' => 500, 'message' => '更新配置失败: ' . $e->getMessage()]);
        }
    }

    /**
     * 批量更新配置
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    #[RequestMapping(path: 'batch-update', methods: ['POST'])]
    public function batchUpdateConfig(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $configs = $request->input('configs', []);
            
            if (!is_array($configs) || empty($configs)) {
                return $response->json(['code' => 400, 'message' => '配置数据格式错误']);
            }

            $result = $this->systemConfigService->setConfigs($configs);
            
            if ($result) {
                return $response->json(['code' => 200, 'message' => '批量更新配置成功']);
            } else {
                return $response->json(['code' => 500, 'message' => '批量更新配置失败']);
            }
        } catch (\Exception $e) {
            $this->logger->error('批量更新配置失败: ' . $e->getMessage());
            return $response->json(['code' => 500, 'message' => '批量更新配置失败: ' . $e->getMessage()]);
        }
    }
}