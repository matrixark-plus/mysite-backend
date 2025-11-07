<?php

namespace App\Service;

use Hyperf\DbConnection\Db;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Carbon\Carbon;

class MindMapService
{
    /**
     * @var \Redis
     */
    protected $redis;
    
    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(RedisFactory::class)->get('default');
    }
    
    /**
     * 获取根节点列表
     * 
     * @param array $params 查询参数
     * @return array 根节点列表
     */
    public function getRootNodes($params = [])
    {
        try {
            // 构建缓存键
            $cacheKey = 'mind_map:root_nodes:' . md5(json_encode($params));
            
            // 尝试从缓存获取
            $cached = $this->redis->get($cacheKey);
            if ($cached) {
                return json_decode($cached, true);
            }
            
            // 构建查询
            $query = Db::table('mind_map_nodes')
                ->where('parent_id', 0) // 根节点的父ID为0
                ->where('status', 1);   // 只获取已发布的
            
            // 应用筛选条件
            if (isset($params['keyword']) && $params['keyword']) {
                $query->where('title', 'like', "%{$params['keyword']}%");
            }
            
            // 应用排序
            $sortBy = $params['sort_by'] ?? 'created_at';
            $sortOrder = $params['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);
            
            // 分页
            $page = $params['page'] ?? 1;
            $pageSize = $params['page_size'] ?? 20;
            
            // 获取总数
            $total = $query->count();
            
            // 获取列表
            $list = $query
                ->forPage($page, $pageSize)
                ->select([
                    'id', 'title', 'description', 'cover_image', 
                    'created_at', 'updated_at', 'view_count'
                ])
                ->get();
            
            // 格式化结果
            $result = [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'list' => $list
            ];
            
            // 设置缓存，10分钟过期
            $this->redis->set($cacheKey, json_encode($result), 600);
            
            return $result;
        } catch (\Exception $e) {
            logger()->error('获取脑图根节点列表异常: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取脑图数据
     * 
     * @param int $rootId 根节点ID
     * @param bool $includeContent 是否包含节点内容
     * @return array 脑图数据结构
     */
    public function getMindMapData($rootId, $includeContent = false)
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
            $rootNode = Db::table('mind_map_nodes')
                ->where('id', $rootId)
                ->where('status', 1)
                ->first();
            
            if (!$rootNode) {
                throw new \Exception('脑图不存在或未发布');
            }
            
            // 构建脑图数据结构
            $mindMapData = [
                'root' => $this->formatNode($rootNode, $includeContent),
                'nodes' => $this->getAllNodes($rootId, $includeContent),
                'edges' => $this->getEdges($rootId)
            ];
            
            // 设置缓存，30分钟过期
            $this->redis->set($cacheKey, json_encode($mindMapData), 1800);
            
            // 异步增加浏览次数
            $this->incrementViewCount($rootId);
            
            return $mindMapData;
        } catch (\Exception $e) {
            logger()->error('获取脑图数据异常: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 获取所有节点
     * 
     * @param int $rootId 根节点ID
     * @param bool $includeContent 是否包含内容
     * @return array
     */
    protected function getAllNodes($rootId, $includeContent = false)
    {
        $nodes = Db::table('mind_map_nodes')
            ->where(function ($query) use ($rootId) {
                $query->where('id', $rootId) // 包含根节点
                      ->orWhere('root_id', $rootId); // 以及其所有子节点
            })
            ->where('status', 1)
            ->get();
        
        $result = [];
        foreach ($nodes as $node) {
            $result[$node->id] = $this->formatNode($node, $includeContent);
        }
        
        return $result;
    }
    
    /**
     * 获取节点间的关系
     * 
     * @param int $rootId 根节点ID
     * @return array
     */
    protected function getEdges($rootId)
    {
        $edges = Db::table('mind_map_edges')
            ->where('root_id', $rootId)
            ->get();
        
        $result = [];
        foreach ($edges as $edge) {
            $result[] = [
                'id' => $edge->id,
                'source' => $edge->source_id,
                'target' => $edge->target_id,
                'label' => $edge->label,
                'direction' => $edge->direction
            ];
        }
        
        return $result;
    }
    
    /**
     * 格式化节点数据
     * 
     * @param mixed $node 节点数据
     * @param bool $includeContent 是否包含内容
     * @return array
     */
    protected function formatNode($node, $includeContent = false)
    {
        $formatted = [
            'id' => $node->id,
            'parent_id' => $node->parent_id,
            'root_id' => $node->root_id,
            'title' => $node->title,
            'description' => $node->description,
            'type' => $node->type,
            'level' => $node->level,
            'sort' => $node->sort,
            'color' => $node->color,
            'created_at' => $node->created_at,
            'updated_at' => $node->updated_at
        ];
        
        // 是否包含内容
        if ($includeContent) {
            $formatted['content'] = $node->content;
        }
        
        return $formatted;
    }
    
    /**
     * 增加浏览次数
     * 
     * @param int $nodeId 节点ID
     */
    protected function incrementViewCount($nodeId)
    {
        // 使用异步任务增加浏览次数
        // 这里简化处理，直接更新数据库
        Db::table('mind_map_nodes')
            ->where('id', $nodeId)
            ->increment('view_count');
        
        // 清除缓存
        $this->redis->del('mind_map:root_nodes:*');
        $this->redis->del('mind_map:data:' . $nodeId . ':with_content');
        $this->redis->del('mind_map:data:' . $nodeId . ':no_content');
    }
}