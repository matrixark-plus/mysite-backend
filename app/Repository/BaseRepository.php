<?php

declare(strict_types=1);
/**
 * Repository基类
 * 提供通用的数据访问方法和统一的错误处理
 */

namespace App\Repository;

use App\Model\Model;
use Hyperf\DbConnection\Db;
use Psr\Log\LoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Exception;

abstract class BaseRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;
    
    /**
     * 模型实例
     * @var Model|null
     */
    protected $model = null;
    
    /**
     * 表名
     * @var string
     */
    protected $table = '';
    
    /**
     * 构造函数
     */
    public function __construct()
    {
        // 如果定义了模型类，则初始化模型实例
        $modelClass = $this->getModel();
        if ($modelClass && class_exists($modelClass)) {
            $this->model = new $modelClass();
        }
        
        // 如果没有设置表名且有模型实例，则从模型获取
        if (empty($this->table) && $this->model instanceof Model) {
            $this->table = $this->model->table;
        }
    }
    
    /**
     * 获取模型类名
     * @return string 模型类名
     */
    abstract protected function getModel(): string;
    
    /**
     * 通用数据库操作错误处理
     * 
     * @param callable $callback 要执行的数据库操作
     * @param string $errorMsg 错误消息
     * @param array $context 上下文信息
     * @param mixed $default 默认返回值
     * @return mixed 操作结果或默认值
     */
    protected function handleDatabaseOperation(callable $callback, string $errorMsg, array $context = [], $default = null)
    {
        try {
            return $callback();
        } catch (Exception $e) {
            $this->logger->error($errorMsg . ': ' . $e->getMessage(), $context);
            return $default;
        }
    }
    
    /**
     * 根据ID查找记录（内部方法，供子类调用）
     * 
     * @param int $id 记录ID
     * @param string $table 表名（可选）
     * @param string $idField ID字段名（默认'id'）
     * @return array|null 记录数据或null
     */
    protected function findByIdInternal(int $id, string $table = null, string $idField = 'id'): ?array
    {
        $targetTable = $table ?? $this->table;
        
        return $this->handleDatabaseOperation(
            function () use ($targetTable, $id, $idField) {
                if (empty($targetTable)) {
                    throw new Exception('表名未设置');
                }
                
                $result = Db::table($targetTable)
                    ->where($idField, $id)
                    ->first();
                    
                return $result ? (array)$result : null;
            },
            '根据ID查找记录失败',
            ['table' => $targetTable, 'id' => $id, 'id_field' => $idField],
            null
        );
    }
    
    /**
     * 根据字段值查找记录（内部方法，供子类调用）
     * 
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @param string $table 表名（可选）
     * @param array $columns 返回字段（默认全部）
     * @return array|null 记录数据或null
     */
    protected function findByFieldInternal(string $field, $value, string $table = null, array $columns = ['*']): ?array
    {
        $targetTable = $table ?? $this->table;
        
        return $this->handleDatabaseOperation(
            function () use ($targetTable, $field, $value, $columns) {
                if (empty($targetTable)) {
                    throw new Exception('表名未设置');
                }
                
                $result = Db::table($targetTable)
                    ->where($field, $value)
                    ->select($columns)
                    ->first();
                    
                return $result ? (array)$result : null;
            },
            '根据字段值查找记录失败',
            ['table' => $targetTable, 'field' => $field, 'value' => $value],
            null
        );
    }
    
    /**
     * 根据条件查找记录列表（内部方法，供子类调用）
     * 
     * @param array $conditions 查询条件
     * @param array $columns 返回字段（默认全部）
     * @param array $orders 排序条件
     * @param string $table 表名（可选）
     * @param int|null $limit 限制数量
     * @return array 记录列表
     */
    protected function findWithConditionsInternal(array $conditions, array $columns = ['*'], array $orders = [], string $table = null, int $limit = null): array
    {
        $targetTable = $table ?? $this->table;
        
        return $this->handleDatabaseOperation(
            function () use ($targetTable, $conditions, $columns, $orders, $limit) {
                if (empty($targetTable)) {
                    throw new Exception('表名未设置');
                }
                
                $query = Db::table($targetTable)->select($columns);
                
                // 添加查询条件
                foreach ($conditions as $key => $value) {
                    if (is_array($value) && isset($value[0]) && in_array(strtolower($value[0]), ['in', 'not in', 'between', 'like'])) {
                        // 处理特殊操作符
                        $operator = strtolower($value[0]);
                        $actualValue = $value[1] ?? null;
                        
                        switch ($operator) {
                            case 'in':
                                $query->whereIn($key, $actualValue);
                                break;
                            case 'not in':
                                $query->whereNotIn($key, $actualValue);
                                break;
                            case 'between':
                                if (is_array($actualValue) && count($actualValue) >= 2) {
                                    $query->whereBetween($key, [$actualValue[0], $actualValue[1]]);
                                }
                                break;
                            case 'like':
                                $query->where($key, 'like', $actualValue);
                                break;
                        }
                    } else {
                        // 普通条件
                        $query->where($key, $value);
                    }
                }
                
                // 添加排序
                foreach ($orders as $field => $direction) {
                    $query->orderBy($field, $direction);
                }
                
                // 添加限制
                if ($limit !== null && $limit > 0) {
                    $query->limit($limit);
                }
                
                $results = $query->get();
                return $results->toArray();
            },
            '根据条件查找记录列表失败',
            ['table' => $targetTable, 'conditions' => $conditions, 'orders' => $orders],
            []
        );
    }
    
    /**
     * 根据ID查找记录
     * 
     * @param int $id 记录ID
     * @return array|null 记录数据或null
     */
    public function findById(int $id): ?array
    {
        return $this->findByIdInternal($id);
    }
    
    /**
     * 根据条件查找记录
     * 
     * @param array $conditions 查询条件
     * @return array|null 记录数据或null
     */
    public function findOne(array $conditions): ?array
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            $query = Db::table($this->table);
            
            foreach ($conditions as $key => $value) {
                $query->where($key, $value);
            }
            
            $result = $query->first();
            return $result ? (array)$result : null;
        } catch (Exception $e) {
            $this->logger->error('根据条件查找记录失败', [
                'table' => $this->table,
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * 根据条件查找记录列表
     * 
     * @param array $conditions 查询条件
     * @param array $orders 排序条件
     * @return array 记录列表
     */
    public function findAll(array $conditions = [], array $orders = []): array
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            $query = Db::table($this->table);
            
            foreach ($conditions as $key => $value) {
                $query->where($key, $value);
            }
            
            foreach ($orders as $field => $direction) {
                $query->orderBy($field, $direction);
            }
            
            $results = $query->get();
            return $results->toArray();
        } catch (Exception $e) {
            $this->logger->error('查询记录列表失败', [
                'table' => $this->table,
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
    
    /**
     * 分页查询
     * 
     * @param array $conditions 查询条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @param array $orders 排序条件
     * @return array 分页结果
     */
    public function paginate(array $conditions = [], int $page = 1, int $pageSize = 10, array $orders = []): array
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            $query = Db::table($this->table);
            
            foreach ($conditions as $key => $value) {
                $query->where($key, $value);
            }
            
            foreach ($orders as $field => $direction) {
                $query->orderBy($field, $direction);
            }
            
            // 获取总数
            $total = $query->count();
            
            // 分页查询
            $items = $query
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get()
                ->toArray();
            
            return [
                'total' => $total,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => ceil($total / $pageSize),
                'data' => $items
            ];
        } catch (Exception $e) {
            $this->logger->error('分页查询失败', [
                'table' => $this->table,
                'conditions' => $conditions,
                'page' => $page,
                'page_size' => $pageSize,
                'error' => $e->getMessage()
            ]);
            return [
                'total' => 0,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => 0,
                'data' => []
            ];
        }
    }
    
    /**
     * 统计记录数
     * 
     * @param array $conditions 查询条件
     * @return int 记录数
     */
    public function count(array $conditions = []): int
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            $query = Db::table($this->table);
            
            foreach ($conditions as $key => $value) {
                $query->where($key, $value);
            }
            
            return $query->count();
        } catch (Exception $e) {
            $this->logger->error('统计记录数失败', [
                'table' => $this->table,
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
    
    /**
     * 创建记录
     * 
     * @param array $data 记录数据
     * @return bool 是否创建成功
     */
    public function create(array $data): bool
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            return Db::table($this->table)->insert($data) > 0;
        } catch (Exception $e) {
            $this->logger->error('创建记录失败', [
                'table' => $this->table,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 更新记录
     * 
     * @param int $id 记录ID
     * @param array $data 更新数据
     * @return bool 是否更新成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            return Db::table($this->table)
                ->where('id', $id)
                ->update($data) > 0;
        } catch (Exception $e) {
            $this->logger->error('更新记录失败', [
                'table' => $this->table,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 根据条件更新记录
     * 
     * @param array $conditions 查询条件
     * @param array $data 更新数据
     * @return bool 是否更新成功
     */
    public function updateBy(array $conditions, array $data): bool
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            $query = Db::table($this->table);
            
            foreach ($conditions as $key => $value) {
                $query->where($key, $value);
            }
            
            return $query->update($data) > 0;
        } catch (Exception $e) {
            $this->logger->error('根据条件更新记录失败', [
                'table' => $this->table,
                'conditions' => $conditions,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 删除记录
     * 
     * @param int $id 记录ID
     * @return bool 是否删除成功
     */
    public function delete(int $id): bool
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            return Db::table($this->table)
                ->where('id', $id)
                ->delete() > 0;
        } catch (Exception $e) {
            $this->logger->error('删除记录失败', [
                'table' => $this->table,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 根据条件删除记录
     * 
     * @param array $conditions 查询条件
     * @return bool 是否删除成功
     */
    public function deleteBy(array $conditions): bool
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            $query = Db::table($this->table);
            
            foreach ($conditions as $key => $value) {
                $query->where($key, $value);
            }
            
            return $query->delete() > 0;
        } catch (Exception $e) {
            $this->logger->error('根据条件删除记录失败', [
                'table' => $this->table,
                'conditions' => $conditions,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 执行事务操作
     * 
     * @param callable $callback 事务回调函数
     * @return mixed 回调函数的返回值
     * @throws \Throwable
     */
    public function transaction(callable $callback)
    {
        return Db::transaction($callback);
    }
    
    /**
     * 分批处理大量数据
     * 避免一次性加载全部数据到内存中，适用于处理大量数据的场景
     * 
     * @param callable $callback 处理回调函数，接收每批数据
     * @param array $conditions 查询条件
     * @param int $chunkSize 每批处理的数据量
     * @param array $columns 返回字段（默认全部）
     * @param array $orders 排序条件
     * @return bool 是否成功执行
     */
    public function chunk(callable $callback, array $conditions = [], int $chunkSize = 1000, array $columns = ['*'], array $orders = []): bool
    {
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            $query = Db::table($this->table)->select($columns);
            
            // 添加查询条件
            foreach ($conditions as $key => $value) {
                if (is_array($value) && isset($value[0]) && in_array(strtolower($value[0]), ['in', 'not in', 'between', 'like'])) {
                    // 处理特殊操作符
                    $operator = strtolower($value[0]);
                    $actualValue = $value[1] ?? null;
                    
                    switch ($operator) {
                        case 'in':
                            $query->whereIn($key, $actualValue);
                            break;
                        case 'not in':
                            $query->whereNotIn($key, $actualValue);
                            break;
                        case 'between':
                            if (is_array($actualValue) && count($actualValue) >= 2) {
                                $query->whereBetween($key, [$actualValue[0], $actualValue[1]]);
                            }
                            break;
                        case 'like':
                            $query->where($key, 'like', $actualValue);
                            break;
                    }
                } else {
                    // 普通条件
                    $query->where($key, $value);
                }
            }
            
            // 添加排序
            foreach ($orders as $field => $direction) {
                $query->orderBy($field, $direction);
            }
            
            // 使用chunk方法分批处理数据
            return $query->chunk($chunkSize, $callback);
        } catch (Exception $e) {
            $this->logger->error('分批处理数据失败', [
                'table' => $this->table,
                'conditions' => $conditions,
                'chunk_size' => $chunkSize,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * 批量插入数据
     * 优化大量数据的插入操作
     * 
     * @param array $dataSet 数据集，二维数组格式
     * @param int $batchSize 每批插入的数据量
     * @return int 成功插入的记录数
     */
    public function bulkInsert(array $dataSet, int $batchSize = 1000): int
    {
        if (empty($dataSet)) {
            return 0;
        }
        
        $totalInserted = 0;
        
        try {
            if (empty($this->table)) {
                throw new Exception('表名未设置');
            }
            
            // 分批插入数据
            foreach (array_chunk($dataSet, $batchSize) as $batch) {
                $inserted = Db::table($this->table)->insert($batch);
                if ($inserted) {
                    $totalInserted += count($batch);
                }
            }
            
            return $totalInserted;
        } catch (Exception $e) {
            $this->logger->error('批量插入数据失败', [
                'table' => $this->table,
                'batch_size' => $batchSize,
                'total_records' => count($dataSet),
                'inserted_records' => $totalInserted,
                'error' => $e->getMessage()
            ]);
            return $totalInserted;
        }
    }
}