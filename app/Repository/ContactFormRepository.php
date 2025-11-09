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

namespace App\Repository;

use Exception;
use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Psr\Log\LoggerInterface;

/**
 * 联系表单数据访问层
 * 封装所有与联系表单数据相关的数据库操作.
 */
class ContactFormRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 根据ID查找联系表单记录.
     *
     * @param int $id 记录ID
     * @return array|null 联系表单数据数组或null
     */
    public function findById(int $id): ?array
    {
        try {
            $result = Db::table('contact_forms')->find($id);
            return is_object($result) ? (array)$result : $result;
        } catch (Exception $e) {
            $this->logger->error('根据ID查找联系表单记录失败: ' . $e->getMessage(), ['contact_form_id' => $id]);
            return null;
        }
    }

    /**
     * 根据条件查询联系表单记录.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @return array|null 联系表单数据数组或null
     */
    public function findBy(array $conditions, array $columns = ['*']): ?array
    {
        try {
            $query = Db::table('contact_forms');
            
            foreach ($conditions as $key => $value) {
                // 处理复杂条件如OR
                if ($key === 'OR' && is_array($value)) {
                    $query = $query->where(function ($q) use ($value) {
                        foreach ($value as $orCondition) {
                            $q->orWhere(...$orCondition);
                        }
                    });
                } else {
                    $query = $query->where($key, $value);
                }
            }
            
            $result = $query->select($columns)->first();
            return is_object($result) ? (array)$result : $result;
        } catch (Exception $e) {
            $this->logger->error('根据条件查询联系表单记录失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return null;
        }
    }

    /**
     * 根据状态获取联系表单记录列表.
     *
     * @param int $status 状态码
     * @param array<string, string> $order 排序条件
     * @return array 联系表单数据数组
     */
    public function findByStatus(int $status, array $order = ['created_at' => 'desc']): array
    {
        try {
            $query = Db::table('contact_forms')->where('status', $status);

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            return $query->get()->toArray();
        } catch (Exception $e) {
            $this->logger->error('根据状态获取联系表单记录失败: ' . $e->getMessage(), ['status' => $status]);
            return [];
        }
    }

    /**
     * 根据条件获取联系表单记录列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @param array<string, string> $order 排序条件
     * @return array 联系表单数据数组
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): array
    {
        try {
            $query = Db::table('contact_forms');

            if (! empty($conditions)) {
                foreach ($conditions as $key => $value) {
                    // 处理复杂条件如OR
                    if ($key === 'OR' && is_array($value)) {
                        $query = $query->where(function ($q) use ($value) {
                            foreach ($value as $orCondition) {
                                $q->orWhere(...$orCondition);
                            }
                        });
                    } else {
                        $query = $query->where($key, $value);
                    }
                }
            }

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            return $query->select($columns)->get()->toArray();
        } catch (Exception $e) {
            $this->logger->error('获取联系表单记录列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return [];
        }
    }

    /**
     * 创建联系表单记录.
     *
     * @param array<string, mixed> $data 表单数据
     * @return array|null 创建的联系表单数据数组或null
     */
    public function create(array $data): ?array
    {
        try {
            return Db::transaction(function () use ($data) {
                $id = Db::table('contact_forms')->insertGetId($data);
                return $this->findById($id);
            });
        } catch (Exception $e) {
            $this->logger->error('创建联系表单记录失败: ' . $e->getMessage(), ['data' => $data]);
            return null;
        }
    }

    /**
     * 更新联系表单记录.
     *
     * @param int $id 记录ID
     * @param array<string, mixed> $data 更新数据
     * @return bool 更新是否成功
     */
    public function update(int $id, array $data): bool
    {
        try {
            return Db::transaction(function () use ($id, $data) {
                $result = Db::table('contact_forms')->where('id', $id)->update($data);
                return $result > 0;
            });
        } catch (Exception $e) {
            $this->logger->error('更新联系表单记录失败: ' . $e->getMessage(), ['contact_form_id' => $id, 'data' => $data]);
            return false;
        }
    }

    /**
     * 标记为已处理.
     *
     * @param int $id 记录ID
     * @param int $processorId 处理人ID
     * @return bool 更新是否成功
     */
    public function markAsProcessed(int $id, int $processorId): bool
    {
        try {
            $data = [
                'status' => 2, // 假设2代表已处理状态
                'processed_by' => $processorId,
                'processed_at' => date('Y-m-d H:i:s')
            ];
            $result = Db::table('contact_forms')->where('id', $id)->update($data);
            return $result > 0;
        } catch (Exception $e) {
            $this->logger->error('标记联系表单为已处理失败: ' . $e->getMessage(), ['contact_form_id' => $id, 'processor_id' => $processorId]);
            return false;
        }
    }

    /**
     * 删除联系表单记录.
     *
     * @param int $id 记录ID
     * @return bool 删除是否成功
     */
    public function delete(int $id): bool
    {
        try {
            return Db::transaction(function () use ($id) {
                $result = Db::table('contact_forms')->where('id', $id)->delete();
                return $result > 0;
            });
        } catch (Exception $e) {
            $this->logger->error('删除联系表单记录失败: ' . $e->getMessage(), ['contact_form_id' => $id]);
            return false;
        }
    }

    /**
     * 统计联系表单记录数量.
     *
     * @param array<string, mixed> $conditions 统计条件
     * @return int 统计结果
     */
    public function count(array $conditions = []): int
    {
        try {
            $query = Db::table('contact_forms');

            if (! empty($conditions)) {
                foreach ($conditions as $key => $value) {
                    if ($key === 'OR' && is_array($value)) {
                        $query = $query->where(function ($q) use ($value) {
                            foreach ($value as $orCondition) {
                                $q->orWhere(...$orCondition);
                            }
                        });
                    } else {
                        $query = $query->where($key, $value);
                    }
                }
            }

            return $query->count();
        } catch (Exception $e) {
            $this->logger->error('统计联系表单记录数量失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return 0;
        }
    }
}