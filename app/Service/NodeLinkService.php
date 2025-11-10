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
use App\Repository\NodeLinkRepository;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 节点链接服务层
 * 处理思维导图节点链接相关的业务逻辑.
 */
class NodeLinkService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var NodeLinkRepository
     */
    protected $nodeLinkRepository;

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
     * 创建节点链接.
     *
     * @param array<string, mixed> $linkData 链接数据
     * @param null|int $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function createNodeLink(array $linkData, ?int $userId = null): array
    {
        try {
            // 验证必要字段
            if (! isset($linkData['source_node_id']) || ! isset($linkData['target_node_id'])) {
                return [
                    'success' => false,
                    'message' => '缺少必要字段: source_node_id 或 target_node_id',
                ];
            }

            // 验证源节点和目标节点是否存在
            $sourceNode = $this->mindmapNodeRepository->findById($linkData['source_node_id']);
            $targetNode = $this->mindmapNodeRepository->findById($linkData['target_node_id']);

            if (! $sourceNode || ! $targetNode) {
                return [
                    'success' => false,
                    'message' => '源节点或目标节点不存在',
                ];
            }

            // 验证两个节点是否属于同一个思维导图
            if ($sourceNode->root_id !== $targetNode->root_id) {
                return [
                    'success' => false,
                    'message' => '源节点和目标节点必须属于同一个思维导图',
                ];
            }

            // 权限验证
            if (! $this->checkMindmapPermission($sourceNode->root_id, $userId)) {
                return [
                    'success' => false,
                    'message' => '无权操作此思维导图',
                ];
            }

            // 设置默认值
            $linkData['link_type'] = $linkData['link_type'] ?? 'normal';
            $linkData['root_id'] = $sourceNode->root_id;

            // 创建链接
            $result = $this->nodeLinkRepository->create($linkData);
            if (! $result) {
                return [
                    'success' => false,
                    'message' => '链接创建失败',
                ];
            }

            return [
                'success' => true,
                'message' => '链接创建成功',
                'data' => [
                    'id' => $result->id,
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('创建节点链接异常: ' . $e->getMessage(), $linkData);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 批量创建节点链接.
     *
     * @param array<array<string, mixed>> $linksData 链接数据数组
     * @param null|int $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function batchCreateNodeLinks(array $linksData, ?int $userId = null): array
    {
        try {
            if (empty($linksData)) {
                return [
                    'success' => false,
                    'message' => '链接数据不能为空',
                ];
            }

            // 验证所有链接属于同一个思维导图
            $rootIds = [];
            foreach ($linksData as $linkData) {
                if (! isset($linkData['source_node_id'])) {
                    continue;
                }
                $sourceNode = $this->mindmapNodeRepository->findById($linkData['source_node_id']);
                if ($sourceNode) {
                    $rootIds[] = $sourceNode->root_id;
                }
            }

            $rootIds = array_unique($rootIds);
            if (count($rootIds) !== 1) {
                return [
                    'success' => false,
                    'message' => '所有链接必须属于同一个思维导图',
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
            $result = $this->nodeLinkRepository->batchCreate($linksData);
            if ($result) {
                return [
                    'success' => true,
                    'message' => "成功创建 {$result} 个链接",
                ];
            }

            return [
                'success' => false,
                'message' => '批量创建失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('批量创建节点链接异常: ' . $e->getMessage(), ['count' => count($linksData)]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 获取思维导图的所有节点链接.
     *
     * @param int $rootId 思维导图根ID
     * @param null|int $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function getMindmapLinks(int $rootId, ?int $userId = null): array
    {
        try {
            // 权限验证
            if (! $this->checkMindmapAccess($rootId, $userId)) {
                return [
                    'success' => false,
                    'message' => '无权访问此思维导图',
                ];
            }

            // 查询属于指定思维导图的所有链接
            $links = $this->nodeLinkRepository->findByRootId($rootId);

            // 转换为数组格式
            $data = [];
            foreach ($links as $link) {
                $data[] = [
                    'id' => $link->id,
                    'root_id' => $link->root_id,
                    'source_node_id' => $link->source_node_id,
                    'target_node_id' => $link->target_node_id,
                    'link_type' => $link->link_type,
                    'label' => $link->label,
                    'color' => $link->color,
                    'created_at' => $link->created_at,
                ];
            }

            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            $this->logger->error('获取思维导图节点链接异常: ' . $e->getMessage(), ['root_id' => $rootId]);
            return [
                'success' => false,
                'message' => '获取链接失败',
            ];
        }
    }

    /**
     * 删除节点链接.
     *
     * @param int $linkId 链接ID
     * @param null|int $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function deleteNodeLink(int $linkId, ?int $userId = null): array
    {
        try {
            // 获取链接信息
            $link = $this->nodeLinkRepository->findById($linkId);
            if (! $link) {
                return [
                    'success' => false,
                    'message' => '链接不存在',
                ];
            }

            // 权限验证
            if (! $this->checkMindmapPermission($link->root_id, $userId)) {
                return [
                    'success' => false,
                    'message' => '无权操作此思维导图',
                ];
            }

            // 删除链接
            $result = $this->nodeLinkRepository->delete($linkId);
            if ($result) {
                return [
                    'success' => true,
                    'message' => '链接删除成功',
                ];
            }

            return [
                'success' => false,
                'message' => '链接删除失败',
            ];
        } catch (Exception $e) {
            $this->logger->error('删除节点链接异常: ' . $e->getMessage(), ['link_id' => $linkId]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 删除与指定节点相关的所有链接.
     *
     * @param int $nodeId 节点ID
     * @param null|int $userId 用户ID（用于权限验证）
     * @return array 操作结果
     */
    public function deleteLinksByNodeId(int $nodeId, ?int $userId = null): array
    {
        try {
            // 获取节点信息
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

            // 删除链接
            $result = $this->nodeLinkRepository->deleteByNodeId($nodeId);
            return [
                'success' => true,
                'message' => "成功删除 {$result} 个链接",
                'data' => [
                    'deleted_count' => $result,
                ],
            ];
        } catch (Exception $e) {
            $this->logger->error('删除节点相关链接异常: ' . $e->getMessage(), ['node_id' => $nodeId]);
            return [
                'success' => false,
                'message' => '系统异常，请稍后重试',
            ];
        }
    }

    /**
     * 检查思维导图操作权限.
     *
     * @param int $rootId 思维导图根ID
     * @param null|int $userId 用户ID
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
     * 检查思维导图访问权限.
     *
     * @param int $rootId 思维导图根ID
     * @param null|int $userId 用户ID
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
