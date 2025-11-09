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

use App\Model\ContactForm;
use Exception;
use Hyperf\Database\Model\Collection;
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
     * @return null|ContactForm 模型对象或null
     */
    public function findById(int $id): ?ContactForm
    {
        try {
            return ContactForm::find($id);
        } catch (Exception $e) {
            $this->logger->error('根据ID查找联系表单记录失败: ' . $e->getMessage(), ['contact_form_id' => $id]);
            return null;
        }
    }

    /**
     * 根据状态获取联系表单记录列表.
     *
     * @param int $status 状态码
     * @param array<string, string> $order 排序条件
     * @return Collection<int, ContactForm> 模型集合
     */
    public function findByStatus(int $status, array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = ContactForm::where('status', $status);

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('根据状态获取联系表单记录失败: ' . $e->getMessage(), ['status' => $status]);
            return new Collection();
        }
    }

    /**
     * 根据条件获取联系表单记录列表.
     *
     * @param array<string, mixed> $conditions 查询条件
     * @param array<string> $columns 查询字段
     * @param array<string, string> $order 排序条件
     * @return Collection<int, ContactForm> 模型集合
     */
    public function findAllBy(array $conditions = [], array $columns = ['*'], array $order = ['created_at' => 'desc']): Collection
    {
        try {
            $query = ContactForm::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            foreach ($order as $field => $direction) {
                $query = $query->orderBy($field, $direction);
            }

            $result = $query->select($columns)->get();
            return $result instanceof Collection ? $result : new Collection();
        } catch (Exception $e) {
            $this->logger->error('获取联系表单记录列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return new Collection();
        }
    }

    /**
     * 创建联系表单记录.
     *
     * @param array<string, mixed> $data 表单数据
     * @return null|ContactForm 创建的模型对象或null
     */
    public function create(array $data): ?ContactForm
    {
        try {
            return ContactForm::create($data);
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
            $result = ContactForm::where('id', $id)->update($data);
            return $result > 0;
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
                'status' => ContactForm::STATUS_PROCESSED,
                'processed_by' => $processorId,
                'processed_at' => date('Y-m-d H:i:s')
            ];
            $result = ContactForm::where('id', $id)->update($data);
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
            $result = ContactForm::destroy($id);
            return $result > 0;
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
            $query = ContactForm::query();

            if (! empty($conditions)) {
                $query = $query->where($conditions);
            }

            return $query->count();
        } catch (Exception $e) {
            $this->logger->error('统计联系表单记录数量失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            return 0;
        }
    }
}