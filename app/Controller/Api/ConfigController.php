<?php

declare(strict_types=1);
/**
 * 配置管理控制器
 */

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Constants\StatusCode;
use App\Controller\Api\Validator\ConfigValidator;
use App\Middleware\JwtAuthMiddleware;
use App\Service\SystemService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="/api/config")
 * @Middleware({JwtAuthMiddleware::class, "admin"})
 */
class ConfigController extends AbstractController
{
    use LogTrait;

    /**
     * @var SystemService
     * @Inject
     */
    protected $systemService;

    /**
     * @var ConfigValidator
     * @Inject
     */
    protected $validator;

    /**
     * 获取配置.
     *
     * @RequestMapping(path="/get", methods={"GET"})
     */
    public function getConfig()
    {
        try {
            $data = $this->request->all();
            $validatedData = $this->validator->validateGetConfig($data);
            $config = $this->systemService->getConfig($validatedData['key'] ?? null);
            return $this->success($config);
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取配置异常', [], $e, 'system');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取配置失败');
        }
    }

    /**
     * 更新配置.
     *
     * @RequestMapping(path="/update", methods={"POST"})
     */
    public function updateConfig()
    {
        try {
            $data = $this->request->all();
            $validatedData = $this->validator->validateUpdateConfig($data);
            
            $result = $this->systemService->updateConfig($validatedData['key'], $validatedData['value']);

            if ($result) {
                return $this->success(null, '配置更新成功');
            }
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '配置更新失败');
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('更新配置异常', [], $e, 'system');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '更新配置失败');
        }
    }

    /**
     * 批量更新配置.
     *
     * @RequestMapping(path="/batch-update", methods={"POST"})
     */
    public function batchUpdateConfig()
    {
        try {
            $data = $this->request->all();
            $validatedData = $this->validator->validateBatchUpdateConfig($data);
            
            $result = $this->systemService->batchUpdateConfig($validatedData['configs']);

            if ($result) {
                return $this->success(null, '配置批量更新成功');
            }
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '配置批量更新失败');
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('批量更新配置异常', [], $e, 'system');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '批量更新配置失败');
        }
    }
}
