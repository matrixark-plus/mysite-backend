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

use App\Repository\MindmapNodeRepository;
use App\Repository\MindmapRootRepository;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 思维导图节点服务层
 * 处理思维导图节点相关的业务逻辑.
 */
class MindmapNodeService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var MindmapNodeRepository
     */
    protected $mindmapNodeRepository;

    /**
     * @Inject
     * @var MindmapRootRepository
     */
    protected $mindmapRootRepository;

    /**
     * 创建节点
     *
     * @param array<string, mixed> $nodeData 节点数据
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function createNode(array $nodeData, ?int $userId = null): array
    {
        try {
            // 验证必要字段
            if (! isset($nodeData['root_id']) || ! isset($nodeData['title'])) {
                return [
                    'success' => false,
                    'message' => '缺少必要字段: root_id 或 title',
                ];
            }

            // 权限验证
            if (! $this->checkMindmapPermission($nodeData['root_id'], $userId)) {
                return [
                    'success' => false,
                    'message' => '无权操作此思维导图',
                ];
            }

            // 如果有父节点，验证父节点是否属于同一个思维导图
            if (isset($nodeData['parent_id']) && $nodeData['parent_id']) {
                $parentNode = $this->mindmapNodeRepository->findById($nodeData['parent_id']);
                if (! $parentNode || $parentNode->root_id !== $nodeData['root_id']) {
                    return [
                        'success' => false,
                        'message' => '父节点不存在或不属于同一个思维导图',
                    ];
                }
            }

            // 设置默认值
            $nodeData['node_type'] = $nodeData['node_type'] ?? 'normal';
            $nodeData['position_x'] = $nodeData['position_x'] ?? 0;
            $nodeData['position_y'] = $nodeData['position_y'] ?? 0;

            // 创建节点
            $result = $this->mindmapNodeRepository->create($nodeData);
            if (! $result) {
                return [
                    'success' => false,
                    'message' => '节点创建失败',
                ];
            }

            return [
                'success' => true,
                'message' => '节点创建成功',
                'data' => [
                    'id' => $result->id,
                    'title' => $result->title,
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('创建思维导图节点异常: ' . $e->getMessage(), $nodeData);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 批量创建节点
     *
     * @param array<array<string, mixed>> $nodesData 节点数据数组
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function batchCreateNodes(array $nodesData, ?int $userId = null): array
    {
        try {
            if (empty($nodesData)) {
                return [
                    'success' => false,
                    'message' => '节点数据不能为空',
                ];
            }

            // 验证所有节点属于同一个思维导图
            $rootIds = array_unique(array_column($nodesData, 'root_id'));
            if (count($rootIds) !== 1) {
                return [
                    'success' => false,
                    'message' => '所有节点必须属于同一个思维导图',
                ];
            }

            // 权限验证
            if (! $this->checkMindmapPermission($rootIds[0], $userId)) {
                return [
                    'success' => false,
                    'message' => '无权操作此思维导图',
                ];
            }

            // 批量创建
            $result = $this->mindmapNodeRepository->batchCreate($nodesData);
            if ($result) {
                return [
                    'success' => true,
                    'message' => "成功创建 {$result} 个节点",
                ];
            }

            return [
                'success' => false,
                'message' => '批量创建失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('批量创建思维导图节点异常: ' . $e->getMessage(), ['count' => count($nodesData)]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 获取思维导图的所有节点
     *
     * @param int $rootId 思维导图根ID
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function getMindmapNodes(int $rootId, ?int $userId = null): array
    {
        try {
            // 权限验证
            if (! $this->checkMindmapAccess($rootId, $userId)) {
                return [
                    'success' => false,
                    'message' => '无权访问此思维导图',
                ];
            }

            $nodes = $this->mindmapNodeRepository->findByRootId($rootId);

            // 转换为数组格式
            $data = [];
            foreach ($nodes as $node) {
                $data[] = [
                    'id' => $node->id,
                    'root_id' => $node->root_id,
                    'parent_id' => $node->parent_id,
                    'title' => $node->title,
                    'content' => $node->content,
                    'node_type' => $node->node_type,
                    'note_id' => $node->note_id,
                    'position_x' => $node->position_x,
                    'position_y' => $node->position_y,
                    'color' => $node->color,
                    'created_at' => $node->created_at,
                    'updated_at' => $node->updated_at,
                ];
            }

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->logger->error('获取思维导图节点异常: ' . $e->getMessage(), ['root_id' => $rootId]);
            return [
                'success' => false,
                'message' => '获取节点失败',
            ];
        }
    }

    /**
     * 更新节点
     *
     * @param int $nodeId 节点ID
     * @param array<string, mixed> $nodeData 更新数据
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function updateNode(int $nodeId, array $data, ?int $userId = null): array
    {
        try {
            // 获取节点
            $node = $this->mindmapNodeRepository->findById($nodeId);
            if (! $node) {
                return [
                    'success' => false,
                    'message' => '节点不存在',
                ];
            }

            // 权限验证
            if (! $this->checkMindmapPermission($node->root_id, $userId)) {
                return [
                    'success' => false,
                    'message' => '无权操作此思维导图',
                ];
            }

            $result = $this->mindmapNodeRepository->update($nodeId, $data);
            if ($result) {
                return [
                    'success' => true,
                    'message' => '节点更新成功',
                ];
            }

            return [
                'success' => false,
                'message' => '节点更新失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('更新思维导图节点异常: ' . $e->getMessage(), ['node_id' => $nodeId, 'data' => $data]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 删除节点
     *
     * @param int $nodeId 节点ID
     * @param int|null $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function deleteNode(int $nodeId, ?int $userId = null): array
    {
        try {
            // 获取节点
            $node = $this->mindmapNodeRepository->findById($nodeId);
            if (! $node) {
                return [
                    'success' => false,
                    'message' => '节点不存在',
                ];
            }

            // 权限验证
            if (! $this->checkMindmapPermission($node->root_id, $userId)) {
                return [
                    'success' => false,
                    'message' => '无权操作此思维导图',
                ];
            }

            // 递归删除子节点
            $this->deleteChildNodes($nodeId);

            // 删除当前节点
            $result = $this->mindmapNodeRepository->delete($nodeId);
            if ($result) {
                return [
                    'success' => true,
                    'message' => '节点删除成功',
                ];
            }

            return [
                'success' => false,
                'message' => '节点删除失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('删除思维导图节点异常: ' . $e->getMessage(), ['node_id' => $nodeId]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 递归删除子节点
     *
     * @param int $parentId 父节点ID
     */
    protected function deleteChildNodes(int $parentId): void
    {
        $children = $this->mindmapNodeRepository->findChildren($parentId);
        foreach ($children as $child) {
            // 递归删除子节点
            $this->deleteChildNodes($child->id);
            // 删除当前子节点
            $this->mindmapNodeRepository->delete($child->id);
        }
    }

    /**
     * 检查思维导图操作权限
     *
     * @param int $rootId 思维导图根ID
     * @param int|null $userId 用户ID
     * @return bool 是否有权限
     */
    protected function checkMindmapPermission(int $rootId, ?int $userId): bool
    {
        if (! $userId) {
            return false;
        }

        $mindmap = $this->mindmapRootRepository->findById($rootId);
        return $mindmap && $mindmap->creator_id === $userId;
    }

    /**
     * 检查思维导图访问权限
     *
     * @param int $rootId 思维导图根ID
     * @param int|null $userId 用户ID
     * @return bool 是否有权限
     */
    protected function checkMindmapAccess(int $rootId, ?int $userId): bool
    {
        $mindmap = $this->mindmapRootRepository->findById($rootId);
        if (! $mindmap) {
            return false;
        }

        // 公开思维导图所有人都可以访问
        if ($mindmap->is_public) {
            return true;
        }

        // 非公开思维导图只有创建者可以访问
        return $userId && $mindmap->creator_id === $userId;
    }
}