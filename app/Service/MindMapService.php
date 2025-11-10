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
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\RedisFactory;
use Psr\Log\LoggerInterface;
use Redis;

class MindMapService
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
     * @var Redis
     */
    protected $redis;

    public function __construct(RedisFactory $redisFactory)
    {
        $this->redis = $redisFactory->get('default');
    }

    /**
     * 获取根节点列表.
     *
     * @param array $params 查询参数
     * @return array 根节点列表
     */
    public function getRootNodes(array $params = []): array
    {
        try {
            // 构建缓存键
            $cacheKey = 'mind_map:root_nodes:' . md5(json_encode($params));

            // 尝试从缓存获取
            $cached = $this->redis->get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }

            // 通过Repository获取数据
            $result = $this->mindmapNodeRepository->getRootNodes($params);

            // 设置缓存，10分钟过期
            $this->redis->set($cacheKey, json_encode($result), 600);

            return $result;
        } catch (Exception $e) {
            $this->logger->error('获取脑图根节点列表异常: ' . $e->getMessage(), ['params' => $params]);
            throw $e;
        }
    }

    /**
     * 获取脑图数据.
     *
     * @param int $rootId 根节点ID
     * @param bool $includeContent 是否包含节点内容
     * @return array 脑图数据结构
     */
    public function getMindMapData(int $rootId, bool $includeContent = false): array
    {
        try {
            // 构建缓存键
            $cacheKey = 'mind_map:data:' . $rootId . ':' . ($includeContent ? 'with_content' : 'no_content');

            // 尝试从缓存获取
            $cached = $this->redis->get($cacheKey);
            if ($cached) {
                // 异步增加浏览次数
                $this->incrementViewCount($rootId);
                return json_decode($cached, true);
            }

            // 检查根节点是否存在且已发布
            $rootNode = $this->mindmapNodeRepository->getById($rootId);

            if (! $rootNode || $rootNode['status'] != 1) {
                throw new Exception('脑图不存在或未发布');
            }

            // 构建脑图数据结构
            $mindMapData = [
                'root' => $this->formatNode($rootNode, $includeContent),
                'nodes' => $this->getAllNodes($rootId, $includeContent),
                'edges' => $this->mindmapNodeRepository->getEdges($rootId),
            ];

            // 设置缓存，30分钟过期
            $this->redis->set($cacheKey, json_encode($mindMapData), 1800);

            // 异步增加浏览次数
            $this->incrementViewCount($rootId);

            return $mindMapData;
        } catch (Exception $e) {
            $this->logger->error('获取脑图数据异常: ' . $e->getMessage(), ['rootId' => $rootId]);
            throw $e;
        }
    }

    /**
     * 获取所有节点.
     *
     * @param int $rootId 根节点ID
     * @param bool $includeContent 是否包含内容
     */
    protected function getAllNodes(int $rootId, bool $includeContent = false): array
    {
        // 通过Repository获取所有节点
        $nodes = $this->mindmapNodeRepository->getAllNodes($rootId);

        $result = [];
        foreach ($nodes as $node) {
            $result[$node['id']] = $this->formatNode($node, $includeContent);
        }

        return $result;
    }

    /**
     * 注意：getEdges方法已移至Repository层，这里不再需要保留.
     */

    /**
     * 格式化节点数据.
     *
     * @param array $node 节点数据
     * @param bool $includeContent 是否包含内容
     */
    protected function formatNode(array $node, bool $includeContent = false): array
    {
        $formatted = [
            'id' => $node['id'],
            'parent_id' => $node['parent_id'],
            'root_id' => $node['root_id'],
            'title' => $node['title'],
            'description' => $node['description'],
            'type' => $node['type'] ?? '',
            'level' => $node['level'] ?? 0,
            'sort' => $node['sort'] ?? 0,
            'color' => $node['color'] ?? '',
            'created_at' => $node['created_at'],
            'updated_at' => $node['updated_at'],
        ];

        // 是否包含内容
        if ($includeContent && isset($node['content'])) {
            $formatted['content'] = $node['content'];
        }

        return $formatted;
    }

    /**
     * 增加浏览次数.
     *
     * @param int $nodeId 节点ID
     */
    protected function incrementViewCount(int $nodeId): void
    {
        try {
            // 通过Repository增加浏览次数
            $this->mindmapNodeRepository->incrementViewCount($nodeId);

            // 清除缓存
            $this->redis->del('mind_map:root_nodes:*');
            $this->redis->del('mind_map:data:' . $nodeId . ':with_content');
            $this->redis->del('mind_map:data:' . $nodeId . ':no_content');
        } catch (Exception $e) {
            // 记录错误但不影响主流程
            $this->logger->error('增加浏览次数失败: ' . $e->getMessage(), ['nodeId' => $nodeId]);
        }
    }
}
