<?php

namespace App\Service;

use App\Repository\WorkRepository;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 作品服务类
 */
class WorkService
{
    /**
     * @Inject
     * @var WorkRepository
     */
    protected $workRepository;

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
     * 获取作品列表
     * @param array $params 查询参数
     * @param int|null $userId 用户ID（可选）
     * @return array 分页结果
     */
    public function getWorks(array $params, ?int $userId = null): array
    {
        try {
            $page = $params['page'] ?? 1;
            $perPage = $params['per_page'] ?? 10;
            $categoryId = $params['category_id'] ?? null;
            $keyword = $params['keyword'] ?? '';
            
            $query = [
                'category_id' => $categoryId,
                'keyword' => $keyword,
                'user_id' => $userId, // 登录用户可以查看自己的非公开作品
            ];
            
            $works = $this->workRepository->findAllBy($query, $page, $perPage);
            
            // 处理图片数组
            if (!empty($works['data'])) {
                foreach ($works['data'] as &$work) {
                    if (!empty($work['images'])) {
                        $work['images'] = json_decode($work['images'], true);
                    }
                }
            }
            
            return $works;
        } catch (\Exception $e) {
            $this->logger->error('获取作品列表失败: ' . $e->getMessage(), ['params' => $params, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 根据ID获取作品
     * @param int $id 作品ID
     * @param int|null $userId 用户ID（可选）
     * @return array|null 作品信息
     */
    public function getWorkById(int $id, ?int $userId = null): ?array
    {
        try {
            $work = $this->workRepository->findById($id, $userId);
            
            // 处理图片数组
            if ($work && !empty($work['images'])) {
                $work['images'] = json_decode($work['images'], true);
            }
            
            return $work;
        } catch (\Exception $e) {
            $this->logger->error('获取作品详情失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 创建作品
     * @param int $userId 用户ID
     * @param array $data 作品数据
     * @return array 创建的作品信息
     */
    public function createWork(int $userId, array $data): array
    {
        try {
            // 验证数据
            $this->validateWorkData($data);
            
            // 验证分类是否存在
            $category = $this->workRepository->findCategoryById($data['category_id']);
            if (!$category) {
                throw new \InvalidArgumentException('作品分类不存在');
            }
            
            // 设置默认值
            $data['user_id'] = $userId;
            $data['is_public'] = $data['is_public'] ?? true;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // 创建作品
            $work = $this->workRepository->create($data);
            
            // 处理图片数组
            if (!empty($work['images'])) {
                $work['images'] = json_decode($work['images'], true);
            }
            
            $this->logger->info('创建作品成功', ['id' => $work['id'], 'user_id' => $userId]);
            
            return $work;
        } catch (\Exception $e) {
            $this->logger->error('创建作品失败: ' . $e->getMessage(), ['user_id' => $userId, 'data' => $data]);
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
    public function updateWork(int $id, int $userId, array $data): array
    {
        try {
            // 验证数据
            if (!empty($data)) {
                $this->validateWorkData($data, false);
            }
            
            // 如果更新分类，验证分类是否存在
            if (isset($data['category_id'])) {
                $category = $this->workRepository->findCategoryById($data['category_id']);
                if (!$category) {
                    throw new \InvalidArgumentException('作品分类不存在');
                }
            }
            
            // 设置更新时间
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // 更新作品
            $work = $this->workRepository->update($id, $userId, $data);
            
            // 处理图片数组
            if (!empty($work['images'])) {
                $work['images'] = json_decode($work['images'], true);
            }
            
            $this->logger->info('更新作品成功', ['id' => $id, 'user_id' => $userId]);
            
            return $work;
        } catch (\Exception $e) {
            $this->logger->error('更新作品失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * 删除作品
     * @param int $id 作品ID
     * @param int $userId 用户ID
     * @return bool 删除结果
     */
    public function deleteWork(int $id, int $userId): bool
    {
        try {
            $result = $this->workRepository->delete($id, $userId);
            
            if ($result) {
                $this->logger->info('删除作品成功', ['id' => $id, 'user_id' => $userId]);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('删除作品失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 获取作品分类列表
     * @return array 分类列表
     */
    public function getCategories(): array
    {
        try {
            return $this->workRepository->findAllCategories();
        } catch (\Exception $e) {
            $this->logger->error('获取作品分类列表失败: ' . $e->getMessage());
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
            // 设置默认值
            $data['sort_order'] = $data['sort_order'] ?? 0;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $category = $this->workRepository->createCategory($data);
            
            $this->logger->info('创建作品分类成功', ['id' => $category['id'], 'name' => $category['name']]);
            
            return $category;
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
            // 设置更新时间
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            $category = $this->workRepository->updateCategory($id, $data);
            
            if ($category) {
                $this->logger->info('更新作品分类成功', ['id' => $id, 'name' => $category['name']]);
            }
            
            return $category;
        } catch (\Exception $e) {
            $this->logger->error('更新作品分类失败: ' . $e->getMessage(), ['id' => $id, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * 删除作品分类
     * @param int $id 分类ID
     * @return bool 删除结果
     */
    public function deleteCategory(int $id): bool
    {
        try {
            $result = $this->workRepository->deleteCategory($id);
            
            if ($result) {
                $this->logger->info('删除作品分类成功', ['id' => $id]);
            }
            
            return $result;
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
    public function getWorkCountByCategory(int $categoryId): int
    {
        try {
            return $this->workRepository->countWorksByCategory($categoryId);
        } catch (\Exception $e) {
            $this->logger->error('获取分类作品数量失败: ' . $e->getMessage(), ['category_id' => $categoryId]);
            throw $e;
        }
    }

    /**
     * 验证作品数据
     * @param array $data 作品数据
     * @param bool $requireAll 是否所有字段都必填
     */
    protected function validateWorkData(array $data, bool $requireAll = true): void
    {
        // 验证标题
        if (isset($data['title']) || $requireAll) {
            if (empty($data['title']) && $requireAll) {
                throw new \InvalidArgumentException('作品标题不能为空');
            }
            if (isset($data['title']) && mb_strlen($data['title']) > 255) {
                throw new \InvalidArgumentException('作品标题不能超过255个字符');
            }
        }
        
        // 验证描述
        if (isset($data['description']) || $requireAll) {
            if (empty($data['description']) && $requireAll) {
                throw new \InvalidArgumentException('作品描述不能为空');
            }
        }
        
        // 验证分类ID
        if (isset($data['category_id']) || $requireAll) {
            if (empty($data['category_id']) && $requireAll) {
                throw new \InvalidArgumentException('作品分类不能为空');
            }
            if (isset($data['category_id']) && (!is_numeric($data['category_id']) || $data['category_id'] <= 0)) {
                throw new \InvalidArgumentException('作品分类ID无效');
            }
        }
        
        // 验证URL
        $urlFields = ['cover_image', 'demo_url', 'source_url'];
        foreach ($urlFields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (mb_strlen($data[$field]) > 500) {
                    throw new \InvalidArgumentException($field . '长度不能超过500个字符');
                }
                if (!filter_var($data[$field], FILTER_VALIDATE_URL) && !empty($data[$field])) {
                    throw new \InvalidArgumentException($field . '必须是有效的URL');
                }
            }
        }
        
        // 验证公开状态
        if (isset($data['is_public'])) {
            if (!is_bool($data['is_public'])) {
                throw new \InvalidArgumentException('公开状态必须是布尔值');
            }
        }
    }
}