\u003c?php

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

use App\Model\Contact;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Database\Query\Builder;
use Psr\Log\LoggerInterface;

/**
 * 联系表单仓库类.
 */
class ContactRepository extends BaseRepository
{
    /**
     * @Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * 获取模型实例.
     */
    protected function getModel(): string
    {
        return Contact::class;
    }

    /**
     * 根据ID查找联系记录.
     *
     * @param int $id
     * @return Contact|null
     */
    public function findById(int $id): ?Contact
    {
        try {
            return $this-\u003emodel-\u003efind($id);
        } catch (\Throwable $e) {
            $this-\u003elogger-\u003eerror('查找联系记录失败: ' . $e-\u003egetMessage(), ['id' =\u003e $id]);
            return null;
        }
    }

    /**
     * 创建联系记录.
     *
     * @param array $data
     * @return Contact|null
     */
    public function create(array $data): ?Contact
    {
        try {
            return $this-\u003emodel-\u003ecreate($data);
        } catch (\Throwable $e) {
            $this-\u003elogger-\u003eerror('创建联系记录失败: ' . $e-\u003egetMessage(), $data);
            return null;
        }
    }

    /**
     * 更新联系记录.
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update(int $id, array $data): bool
    {
        try {
            return $this-\u003emodel-\u003ewhere('id', $id)-\u003eupdate($data) > 0;
        } catch (\Throwable $e) {
            $this-\u003elogger-\u003eerror('更新联系记录失败: ' . $e-\u003egetMessage(), ['id' =\u003e $id, 'data' =\u003e $data]);
            return false;
        }
    }

    /**
     * 标记联系记录为已处理.
     *
     * @param int $id
     * @param array $additionalData
     * @return bool
     */
    public function markAsProcessed(int $id, array $additionalData = []): bool
    {
        $data = array_merge([
            'status' =\u003e Contact::STATUS_PROCESSED,
            'processed_at' =\u003e date('Y-m-d H:i:s'),
            'updated_at' =\u003e date('Y-m-d H:i:s'),
        ], $additionalData);
        
        return $this-\u003eupdate($id, $data);
    }

    /**
     * 获取联系记录列表.
     *
     * @param int $page
     * @param int $pageSize
     * @param array $filters
     * @return array
     */
    public function getContactList(int $page = 1, int $pageSize = 20, array $filters = []): array
    {
        try {
            $query = $this-\u003ebuildContactListQuery($filters);
            
            // 获取总数
            $total = $query-\u003ecount();
            
            // 获取分页数据
            $list = $query
                -\u003eorderBy('created_at', 'desc')
                -\u003eforPage($page, $pageSize)
                -\u003eget();
            
            return [
                'total' =\u003e $total,
                'page' =\u003e $page,
                'pageSize' =\u003e $pageSize,
                'list' =\u003e $list,
            ];
        } catch (\Throwable $e) {
            $this-\u003elogger-\u003eerror('获取联系记录列表失败: ' . $e-\u003egetMessage(), ['filters' =\u003e $filters]);
            return [
                'total' =\u003e 0,
                'page' =\u003e $page,
                'pageSize' =\u003e $pageSize,
                'list' =\u003e [],
            ];
        }
    }

    /**
     * 构建联系记录列表查询.
     *
     * @param array $filters
     * @return Builder
     */
    protected function buildContactListQuery(array $filters): Builder
    {
        $query = $this-\u003emodel-\u003equery();
        
        // 应用筛选条件
        if (isset($filters['status'])) {
            $query-\u003ewhere('status', $filters['status']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query-\u003ewhere(function ($q) use ($search) {
                $q-\u003ewhere('name', 'like', "%{$search}%")
                    -\u003eorWhere('email', 'like', "%{$search}%")
                    -\u003eorWhere('subject', 'like', "%{$search}%")
                    -\u003eorWhere('message', 'like', "%{$search}%");
            });
        }
        
        if (isset($filters['date_range'])) {
            $dateRange = $filters['date_range'];
            if (isset($dateRange['start']) && $dateRange['start']) {
                $query-\u003ewhere('created_at', '>=', $dateRange['start']);
            }
            if (isset($dateRange['end']) && $dateRange['end']) {
                $query-\u003ewhere('created_at', '<=', $dateRange['end']);
            }
        }
        
        return $query;
    }

    /**
     * 获取未处理的联系记录数量.
     *
     * @return int
     */
    public function getUnprocessedCount(): int
    {
        try {
            return $this-\u003emodel-\u003ewhere('status', Contact::STATUS_UNPROCESSED)-\u003ecount();
        } catch (\Throwable $e) {
            $this-\u003elogger-\u003eerror('获取未处理联系记录数量失败: ' . $e-\u003egetMessage());
            return 0;
        }
    }

    /**
     * 删除联系记录.
     *
     * @param int $id 联系记录ID
     * @return bool 删除结果
     */
    public function delete(int $id): bool
    {
        try {
            return $this-\u003emodel-\u003ewhere('id', $id)-\u003edelete() > 0;
        } catch (\Throwable $e) {
            $this-\u003elogger-\u003eerror('删除联系记录失败: ' . $e-\u003egetMessage(), ['id' =\u003e $id]);
            return false;
        }
    }
}