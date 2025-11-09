<?php

namespace App\Repository;

use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 作品仓库类
 */
class WorkRepository
{
    /**
     * @Inject
     * @var LoggerFactory
     */
    protected $loggerFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct()
    {
        $this->logger = $this->loggerFactory->make('work');
    }

    /**
     * 根据条件查询作品列表
     * @param array $conditions 查询条件
     * @param int $page 页码
     * @param int $perPage 每页数量
     * @return array 分页结果
     */
    public function findAllBy(array $conditions, int $page = 1, int $perPage = 10): array
    {
        try {
            $query = Db::table('works')
                ->select(
                    'works.*',
                    'work_categories.name as category_name'
                )
                ->leftJoin('work_categories', 'works.category_id', '=', 'work_categories.id');
            
            // 添加查询条件
            if (isset($conditions['category_id'])) {
                $query->where('works.category_id', $conditions['category_id']);
            }
            
            if (isset($conditions['keyword']) && $conditions['keyword']) {
                $query->where('works.title', 'LIKE', '%' . $conditions['keyword'] . '%')
                    ->orWhere('works.description', 'LIKE', '%' . $conditions['keyword'] . '%');
            }
            
            // 如果用户未登录，只显示公开作品
            // 如果用户已登录，显示公开作品和该用户的所有作品
            if (isset($conditions['user_id']) && $conditions['user_id']) {
                $query->where(function ($q) use ($conditions) {
                    $q->where('works.is_public', 1)
                      ->orWhere('works.user_id', $conditions['user_id']);
                });
            } else {
                $query->where('works.is_public', 1);
            }
            
            // 分页查询
            $total = $query->count();
            $items = $query->orderBy('works.created_at', 'DESC')
                ->offset(($page - 1) * $perPage)
                ->limit($perPage)
                ->get()->toArray();
            
            return [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
                'data' => $items,
            ];
        } catch (\Exception $e) {
            $this->logger->error('查询作品列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            throw $e;
        }
    }

    /**
     * 根据ID查询作品
     * @param int $id 作品ID
     * @param int|null $userId 用户ID（可选）
     * @return array|null 作品信息
     */
    public function findById(int $id, ?int $userId = null): ?array
    {
        try {
            $query = Db::table('works')
                ->select(
                    'works.*',
                    'work_categories.name as category_name',
                    'work_categories.description as category_description'
                )
                ->leftJoin('work_categories', 'works.category_id', '=', 'work_categories.id')
                ->where('works.id', $id);
            
            // 如果用户未登录，只显示公开作品
            // 如果用户已登录，显示公开作品和该用户的所有作品
            if ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('works.is_public', 1)
                      ->orWhere('works.user_id', $userId);
                });
            } else {
                $query->where('works.is_public', 1);
            }
            
            $work = $query->first();
            
            return $work ? (array) $work : null;
        } catch (\Exception $e) {
            $this->logger->error('查询作品详情失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 创建作品
     * @param array $data 作品数据
     * @return array 创建的作品信息
     */
    public function create(array $data): array
    {
        try {
            $workId = Db::table('works')->insertGetId($data);
            
            // 获取创建的作品信息
            $work = Db::table('works')
                ->select(
                    'works.*',
                    'work_categories.name as category_name'
                )
                ->leftJoin('work_categories', 'works.category_id', '=', 'work_categories.id')
                ->where('works.id', $workId)
                ->first();
            
            return (array) $work;
        } catch (\Exception $e) {
            $this->logger->error('创建作品失败: ' . $e->getMessage(), ['data' => $data]);
            throw $e;
        }
    }

    /**
     * 更新作品
     * @param int $id 作品ID
     * @param int $userId 用户ID
     * @param array $data 更新数据
     * @return array 更新后的作品信息
     */
    public function update(int $id, int $userId, array $data): array
    {
        return Db::transaction(function () use ($id, $userId, $data) {
            // 更新作品
            $affected = Db::table('works')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->update($data);
            
            if ($affected === 0) {
                throw new \RuntimeException('作品不存在或无权限操作');
            }
            
            // 获取更新后的作品信息
            $work = Db::table('works')
                ->select(
                    'works.*',
                    'work_categories.name as category_name'
                )
                ->leftJoin('work_categories', 'works.category_id', '=', 'work_categories.id')
                ->where('works.id', $id)
                ->first();
            
            return (array) $work;
        });
    }

    /**
     * 删除作品
     * @param int $id 作品ID
     * @param int $userId 用户ID
     * @return bool 删除结果
     */
    public function delete(int $id, int $userId): bool
    {
        try {
            return Db::table('works')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete() > 0;
        } catch (\Exception $e) {
            $this->logger->error('删除作品失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 获取所有作品分类
     * @return array 分类列表
     */
    public function findAllCategories(): array
    {
        try {
            return Db::table('work_categories')
                ->orderBy('sort_order', 'ASC')
                ->orderBy('created_at', 'DESC')
                ->get()->toArray();
        } catch (\Exception $e) {
            $this->logger->error('获取作品分类列表失败: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 根据ID查询分类
     * @param int $id 分类ID
     * @return array|null 分类信息
     */
    public function findCategoryById(int $id): ?array
    {
        try {
            $category = Db::table('work_categories')->where('id', $id)->first();
            
            return $category ? (array) $category : null;
        } catch (\Exception $e) {
            $this->logger->error('查询分类详情失败: ' . $e->getMessage(), ['id' => $id]);
            throw $e;
        }
    }

    /**
     * 创建作品分类
     * @param array $data 分类数据
     * @return array 创建的分类信息
     */
    public function createCategory(array $data): array
    {
        try {
            $categoryId = Db::table('work_categories')->insertGetId($data);
            
            // 获取创建的分类信息
            $category = Db::table('work_categories')->where('id', $categoryId)->first();
            
            return (array) $category;
        } catch (\Exception $e) {
            $this->logger->error('创建作品分类失败: ' . $e->getMessage(), ['data' => $data]);
            throw $e;
        }
    }

    /**
     * 更新作品分类
     * @param int $id 分类ID
     * @param array $data 更新数据
     * @return array|null 更新后的分类信息
     */
    public function updateCategory(int $id, array $data): ?array
    {
        try {
            $affected = Db::table('work_categories')
                ->where('id', $id)
                ->update($data);
            
            if ($affected === 0) {
                return null;
            }
            
            // 获取更新后的分类信息
            $category = Db::table('work_categories')->where('id', $id)->first();
            
            return (array) $category;
        } catch (\Exception $e) {
            $this->logger->error('更新作品分类失败: ' . $e->getMessage(), ['id' => $id, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * 删除作品分类
     * @param int $id 分类ID
     * @return bool 删除结果
     * @throws \InvalidArgumentException 当分类下有关联作品时抛出异常
     */
    public function deleteCategory(int $id): bool
    {
        try {
            // 检查分类下是否有作品
            $workCount = $this->countWorksByCategory($id);
            if ($workCount > 0) {
                throw new \InvalidArgumentException('该分类下有' . $workCount . '个作品，无法删除');
            }
            
            return Db::table('work_categories')
                ->where('id', $id)
                ->delete() > 0;
        } catch (\InvalidArgumentException $e) {
            // 直接抛出InvalidArgumentException，由上层处理用户友好的错误信息
            throw $e;
        } catch (\Exception $e) {
            $this->logger->error('删除作品分类失败: ' . $e->getMessage(), ['id' => $id]);
            throw $e;
        }
    }

    /**
     * 获取分类下的作品数量
     * @param int $categoryId 分类ID
     * @return int 作品数量
     */
    public function countWorksByCategory(int $categoryId): int
    {
        try {
            return Db::table('works')->where('category_id', $categoryId)->count();
        } catch (\Exception $e) {
            $this->logger->error('获取分类作品数量失败: ' . $e->getMessage(), ['category_id' => $categoryId]);
            throw $e;
        }
    }
}