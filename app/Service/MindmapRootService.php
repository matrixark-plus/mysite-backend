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

namespace App\Service;

use App\Repository\MindmapRootRepository;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 思维导图根节点服务层
 * 处理思维导图根节点相关的业务逻辑.
 */
class MindmapRootService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var MindmapRootRepository
     */
    protected $mindmapRootRepository;

    /**
     * 创建思维导图
     *
     * @param array<string, mixed> $data 思维导图数据
     * @return array 操作结果
     */
    public function createMindmap(array $data): array
    {
        try {
            // 验证必要字段
            if (! isset($data['title']) || empty($data['title'])) {
                return [
                    'success' => false,
                    'message' => '思维导图标题不能为空',
                ];
            }

            // 设置默认值
            $data['is_public'] = $data['is_public'] ?? 0;
            $data['is_active'] = $data['is_active'] ?? 1;

            // 创建思维导图
            $result = $this->mindmapRootRepository->create($data);
            if (! $result) {
                return [
                    'success' => false,
                    'message' => '创建失败，请稍后重试',
                ];
            }

            return [
                'success' => true,
                'message' => '思维导图创建成功',
                'data' => [
                    'id' => $result->id,
                    'title' => $result->title,
                    'created_at' => $result->created_at,
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('创建思维导图异常: ' . $e->getMessage(), $data);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 获取用户的思维导图列表
     *
     * @param int $userId 用户ID
     * @param array<string, mixed> $conditions 额外条件
     * @param array<string, string> $order 排序方式
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 操作结果
     */
    public function getUserMindmaps(int $userId, array $conditions = [], array $order = ['created_at' => 'desc'], int $page = 1, int $limit = 20): array
    {
        try {
            // 添加用户ID条件
            $conditions['creator_id'] = $userId;
            $offset = ($page - 1) * $limit;

            $mindmaps = $this->mindmapRootRepository->findAllBy($conditions, ['*'], $order, $limit, $offset);
            $total = $this->mindmapRootRepository->count($conditions);

            // 转换为数组格式
            $data = [];
            foreach ($mindmaps as $mindmap) {
                $data[] = [
                    'id' => $mindmap->id,
                    'title' => $mindmap->title,
                    'description' => $mindmap->description,
                    'is_public' => $mindmap->is_public,
                    'is_active' => $mindmap->is_active,
                    'created_at' => $mindmap->created_at,
                    'updated_at' => $mindmap->updated_at,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'items' => $data,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('获取用户思维导图列表异常: ' . $e->getMessage(), ['user_id' => $userId] + $conditions);
            return [
                'success' => false,
                'message' => '获取列表失败',
            ];
        }
    }

    /**
     * 获取公开的思维导图列表
     *
     * @param array<string, mixed> $conditions 额外条件
     * @param array<string, string> $order 排序方式
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array 操作结果
     */
    public function getPublicMindmaps(array $conditions = [], array $order = ['created_at' => 'desc'], int $page = 1, int $limit = 20): array
    {
        try {
            $offset = ($page - 1) * $limit;
            $mindmaps = $this->mindmapRootRepository->findPublicMindmaps($conditions, $order, $limit, $offset);
            $total = $this->mindmapRootRepository->count(['is_public' => 1, 'is_active' => 1] + $conditions);

            // 转换为数组格式
            $data = [];
            foreach ($mindmaps as $mindmap) {
                $data[] = [
                    'id' => $mindmap->id,
                    'title' => $mindmap->title,
                    'description' => $mindmap->description,
                    'creator_id' => $mindmap->creator_id,
                    'created_at' => $mindmap->created_at,
                    'updated_at' => $mindmap->updated_at,
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'items' => $data,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit),
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('获取公开思维导图列表异常: ' . $e->getMessage(), $conditions);
            return [
                'success' => false,
                'message' => '获取列表失败',
            ];
        }
    }

    /**
     * 获取思维导图详情
     *
     * @param int $id 思维导图ID
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function getMindmapDetail(int $id, ?int $userId = null): array
    {
        try {
            $mindmap = $this->mindmapRootRepository->findById($id);
            if (! $mindmap) {
                return [
                    'success' => false,
                    'message' => '思维导图不存在',
                ];
            }

            // 权限验证：只有公开的思维导图或创建者可以访问
            if (! $mindmap->is_public && (! $userId || $mindmap->creator_id !== $userId)) {
                return [
                    'success' => false,
                    'message' => '无权访问此思维导图',
                ];
            }

            $data = [
                'id' => $mindmap->id,
                'title' => $mindmap->title,
                'description' => $mindmap->description,
                'creator_id' => $mindmap->creator_id,
                'is_public' => $mindmap->is_public,
                'is_active' => $mindmap->is_active,
                'created_at' => $mindmap->created_at,
                'updated_at' => $mindmap->updated_at,
            ];

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->logger->error('获取思维导图详情异常: ' . $e->getMessage(), ['mindmap_id' => $id]);
            return [
                'success' => false,
                'message' => '获取详情失败',
            ];
        }
    }

    /**
     * 更新思维导图
     *
     * @param int $id 思维导图ID
     * @param array<string, mixed> $data 更新数据
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function updateMindmap(int $id, array $data, ?int $userId = null): array
    {
        try {
            $mindmap = $this->mindmapRootRepository->findById($id);
            if (! $mindmap) {
                return [
                    'success' => false,
                    'message' => '思维导图不存在',
                ];
            }

            // 权限验证：只有创建者可以更新
            if (! $userId || $mindmap->creator_id !== $userId) {
                return [
                    'success' => false,
                    'message' => '无权更新此思维导图',
                ];
            }

            $result = $this->mindmapRootRepository->update($id, $data);
            if ($result) {
                return [
                    'success' => true,
                    'message' => '思维导图更新成功',
                ];
            }

            return [
                'success' => false,
                'message' => '更新失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('更新思维导图异常: ' . $e->getMessage(), ['mindmap_id' => $id, 'data' => $data]);
            return [
                'success' => false,
                'message' => '更新失败',
            ];
        }
    }

    /**
     * 切换思维导图的公开状态
     *
     * @param int $id 思维导图ID
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function togglePublicStatus(int $id, ?int $userId = null): array
    {
        try {
            $mindmap = $this->mindmapRootRepository->findById($id);
            if (! $mindmap) {
                return [
                    'success' => false,
                    'message' => '思维导图不存在',
                ];
            }

            // 权限验证：只有创建者可以切换状态
            if (! $userId || $mindmap->creator_id !== $userId) {
                return [
                    'success' => false,
                    'message' => '无权操作此思维导图',
                ];
            }

            $result = $this->mindmapRootRepository->togglePublicStatus($id);
            if ($result) {
                return [
                    'success' => true,
                    'message' => '状态切换成功',
                    'data' => [
                        'is_public' => $result->is_public,
                    ],
                ];
            }

            return [
                'success' => false,
                'message' => '状态切换失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('切换思维导图公开状态异常: ' . $e->getMessage(), ['mindmap_id' => $id]);
            return [
                'success' => false,
                'message' => '状态切换失败',
            ];
        }
    }
}