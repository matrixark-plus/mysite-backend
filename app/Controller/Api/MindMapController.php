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
use App\Controller\Api\Validator\MindMapValidator;
use App\Service\MindMapService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Validation\ValidationException;

/**
 * @Controller(prefix="/api/mind-map")
 */
class MindMapController extends AbstractController
{
    use LogTrait;

    /**
     * @Inject
     * @var MindMapService
     */
    protected $mindMapService;
    
    /**
     * @Inject
     * @var MindMapValidator
     */
    protected $mindMapValidator;

    /**
     * 获取根节点列表.
     *
     * @RequestMapping(path="/root-nodes", methods={"GET"})
     */
    public function getRootNodes(RequestInterface $request)
    {
        try {
            $params = $request->all();
            // 验证参数
            $validatedParams = $this->mindMapValidator->validateRootNodes($params);
            $result = $this->mindMapService->getRootNodes($validatedParams);
            return $this->success($result);
        } catch (ValidationException $e) {
            return $this->validationError($e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取脑图根节点列表异常', ['message' => $e->getMessage()], $e, 'mindmap');
            return $this->error('获取脑图列表失败');
        }
    }

    /**
     * 获取脑图数据.
     *
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function getMindMapData(int $id, RequestInterface $request)
    {
        try {
            // 验证脑图ID
            $this->mindMapValidator->validateMindMapId($id);
            $includeContent = (bool) $request->input('include_content', false);
            $result = $this->mindMapService->getMindMapData($id, $includeContent);
            return $this->success($result);
        } catch (ValidationException $e) {
            return $this->validationError($e->validator->errors()->first());
        } catch (Exception $e) {
            $this->logError('获取脑图数据异常', ['message' => $e->getMessage(), 'id' => $id], $e, 'mindmap');
            return $this->notFound($e->getMessage() ?: '脑图不存在');
        }
    }
}
