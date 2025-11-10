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

use App\Service\EventDemoService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * 事件驱动架构演示控制器
 * 展示如何通过API接口使用事件系统和异步任务
 * @Controller(prefix="/api/event-demo")
 */
class EventDemoController
{
    /**
     * @Inject
     * @var EventDemoService
     */
    protected $eventDemoService;

    /**
     * 创建实体演示.
     * @RequestMapping(path="create-entity", methods={"POST"})
     */
    public function createEntity(RequestInterface $request, ResponseInterface $response)
    {
        $entityType = $request->input('entity_type');
        $entityData = $request->input('entity_data', []);

        if (empty($entityType)) {
            return $response->json([
                'code' => 400,
                'message' => '实体类型不能为空',
            ]);
        }

        $result = $this->eventDemoService->createEntity($entityType, $entityData);

        return $response->json([
            'code' => $result['success'] ? 200 : 400,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * 更新实体演示.
     * @RequestMapping(path="update-entity", methods={"PUT"})
     */
    public function updateEntity(RequestInterface $request, ResponseInterface $response)
    {
        $entityType = $request->input('entity_type');
        $entityId = (int) $request->input('entity_id');
        $updateData = $request->input('update_data', []);

        if (empty($entityType) || $entityId <= 0) {
            return $response->json([
                'code' => 400,
                'message' => '实体类型和ID不能为空',
            ]);
        }

        $result = $this->eventDemoService->updateEntity($entityType, $entityId, $updateData);

        return $response->json([
            'code' => $result['success'] ? 200 : 400,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * 删除实体演示.
     * @RequestMapping(path="delete-entity", methods={"DELETE"})
     */
    public function deleteEntity(RequestInterface $request, ResponseInterface $response)
    {
        $entityType = $request->input('entity_type');
        $entityId = (int) $request->input('entity_id');

        if (empty($entityType) || $entityId <= 0) {
            return $response->json([
                'code' => 400,
                'message' => '实体类型和ID不能为空',
            ]);
        }

        $result = $this->eventDemoService->deleteEntity($entityType, $entityId);

        return $response->json([
            'code' => $result['success'] ? 200 : 400,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * 创建评论演示.
     * @RequestMapping(path="create-comment", methods={"POST"})
     */
    public function createComment(RequestInterface $request, ResponseInterface $response)
    {
        $commentData = $request->input('comment_data', []);

        if (empty($commentData)) {
            return $response->json([
                'code' => 400,
                'message' => '评论数据不能为空',
            ]);
        }

        $result = $this->eventDemoService->createComment($commentData);

        return $response->json([
            'code' => $result['success'] ? 200 : 400,
            'message' => $result['message'],
            'data' => $result,
        ]);
    }

    /**
     * 批量处理演示.
     * @RequestMapping(path="batch-process", methods={"POST"})
     */
    public function batchProcess(RequestInterface $request, ResponseInterface $response)
    {
        $items = $request->input('items', []);

        if (! is_array($items) || empty($items)) {
            return $response->json([
                'code' => 400,
                'message' => '批量处理项目不能为空',
            ]);
        }

        $result = $this->eventDemoService->processBatchItems($items);

        return $response->json([
            'code' => 200,
            'message' => '批量处理完成',
            'data' => $result,
        ]);
    }
}
