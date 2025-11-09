\u003c?php

namespace App\Service;

use App\Repository\NodeLinksRepository;
use App\Repository\MindmapNodeRepository;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 节点链接服务层
 * 处理节点链接相关的业务逻辑
 */
class NodeLinksService
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Inject
     * @var NodeLinksRepository
     */
    protected $nodeLinksRepository;

    /**
     * @Inject
     * @var MindmapNodeRepository
     */
    protected $mindmapNodeRepository;

    /**
     * 创建节点链接
     * @param array $data
     * @param int $creatorId
     * @return array
     * @throws \Exception
     */
    public function createLink(array $data, int $creatorId): array
    {
        // 验证源节点和目标节点是否存在且属于同一脑图
        $this-\u003evalidateNodes($data['source_node_id'], $data['target_node_id']);

        // 验证链接是否已存在
        $existingLink = $this-\u003enodeLinksRepository-\u003efindByNodes(
            $data['source_node_id'], 
            $data['target_node_id']
        );
        
        if ($existingLink) {
            throw new \Exception('节点间链接已存在');
        }

        try {
            $link = $this-\u003enodeLinksRepository-\u003ecreate($data);
            if (!$link) {
                throw new \Exception('创建链接失败');
            }
            return $link;
        } catch (\Exception $e) {
            $this-\u003elogger-\u003eerror('创建节点链接失败: ' . $e-\u003egetMessage());
            throw $e;
        }
    }

    /**
     * 批量创建节点链接
     * @param array $linksData
     * @param int $creatorId
     * @return array
     * @throws \Exception
     */
    public function batchCreateLinks(array $linksData, int $creatorId): array
    {
        try {
            $results = [];
            foreach ($linksData as $linkData) {
                try {
                    $link = $this-\u003ecreateLink($linkData, $creatorId);
                    $results[] = $link;
                } catch (\Exception $e) {
                    // 单个链接创建失败不影响其他链接
                    $this-\u003elogger-\u003ewarning('单个链接创建失败: ' . $e-\u003egetMessage(), ['linkData' =u003e $linkData]);
                }
            }
            return $results;
        } catch (\Exception $e) {
            $this-\u003elogger-\u003eerror('批量创建节点链接失败: ' . $e-\u003egetMessage());
            throw $e;
        }
    }

    /**
     * 更新节点链接
     * @param int $id
     * @param array $data
     * @param int $creatorId
     * @return bool
     * @throws \Exception
     */
    public function updateLink(int $id, array $data, int $creatorId): bool
    {
        // 验证链接是否存在
        $link = $this-\u003enodeLinksRepository-\u003efindById($id);
        if (!$link) {
            throw new \Exception('链接不存在');
        }

        // 如果更新节点，需要重新验证
        if (isset($data['source_node_id']) || isset($data['target_node_id'])) {
            $sourceNodeId = $data['source_node_id'] ?? $link['source_node_id'];
            $targetNodeId = $data['target_node_id'] ?? $link['target_node_id'];
            $this-\u003evalidateNodes($sourceNodeId, $targetNodeId);
        }

        try {
            return $this-\u003enodeLinksRepository-\u003eupdate($id, $data);
        } catch (\Exception $e) {
            $this-\u003elogger-\u003eerror('更新节点链接失败: ' . $e-\u003egetMessage());
            throw $e;
        }
    }

    /**
     * 删除节点链接
     * @param int $id
     * @param int $creatorId
     * @return bool
     * @throws \Exception
     */
    public function deleteLink(int $id, int $creatorId): bool
    {
        // 验证链接是否存在
        $link = $this-\u003enodeLinksRepository-\u003efindById($id);
        if (!$link) {
            throw new \Exception('链接不存在');
        }

        try {
            return $this-\u003enodeLinksRepository-\u003edelete($id);
        } catch (\Exception $e) {
            $this-\u003elogger-\u003eerror('删除节点链接失败: ' . $e-\u003egetMessage());
            throw $e;
        }
    }

    /**
     * 获取脑图的所有链接
     * @param int $rootId
     * @return array
     */
    public function getLinksByRootId(int $rootId): array
    {
        return $this-\u003enodeLinksRepository-\u003 egetLinksByRootId($rootId);
    }

    /**
     * 验证节点是否有效且属于同一脑图
     * @param int $sourceNodeId
     * @param int $targetNodeId
     * @throws \Exception
     */
    protected function validateNodes(int $sourceNodeId, int $targetNodeId): void
    {
        // 不允许自链接
        if ($sourceNodeId === $targetNodeId) {
            throw new \Exception('不能创建节点自链接');
        }

        // 获取节点信息
        $sourceNode = $this-\u003emindmapNodeRepository-\u003efindById($sourceNodeId);
        $targetNode = $this-\u003emindmapNodeRepository-\u003efindById($targetNodeId);

        // 验证节点是否存在
        if (!$sourceNode) {
            throw new \Exception('源节点不存在');
        }
        if (!$targetNode) {
            throw new \Exception('目标节点不存在');
        }

        // 验证节点是否属于同一脑图
        if ($sourceNode['root_id'] !== $targetNode['root_id']) {
            throw new \Exception('只能在同一脑图内创建节点链接');
        }
    }
}