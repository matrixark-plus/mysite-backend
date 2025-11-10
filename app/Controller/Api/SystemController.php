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

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Controller\Api\Validator\SystemValidator;
use App\Service\SystemService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="/api/system")
 */
class SystemController extends AbstractController
{
    use LogTrait;

    /**
     * @var SystemService
     * @Inject
     */
    protected $systemService;

    /**
     * @Inject
     * @var SystemValidator
     */
    protected $validator;

    /**
     * 获取系统配置.
     *
     * @RequestMapping(path="/config", methods={"GET"})
     */
    public function getSystemConfig(): ResponseInterface
    {
        try {
            $config = $this->systemService->getSystemConfig();
            return $this->success($config);
        } catch (Exception $e) {
            $this->logError('获取系统配置异常', ['message' => $e->getMessage()], $e, 'system');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取系统配置失败');
        }
    }

    /**
     * 获取统计数据.
     *
     * @RequestMapping(path="/statistics", methods={"GET"})
     */
    public function getStatistics(): ResponseInterface
    {
        try {
            $params = $this->request->all();
            $validatedData = $this->validator->validateStatistics($params);
            $statistics = $this->systemService->getStatistics($validatedData);
            return $this->success($statistics);
        } catch (ValidationException $e) {
            return $this->fail(StatusCode::VALIDATION_ERROR, $e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取统计数据异常', ['message' => $e->getMessage()], $e, 'system');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取统计数据失败');
        }
    }
}
