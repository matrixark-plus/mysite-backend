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
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * 笔记仓库类.
 */
class NoteRepository
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
        $this->logger = $this->loggerFactory->make('note');
    }

    /**
     * 根据条件查询笔记列表.
     * @param array $conditions 查询条件
     * @param int $page 页码
     * @param int $perPage 每页数量
     * @return array 分页结果
     */
    public function findAllBy(array $conditions, int $page = 1, int $perPage = 10): array
    {
        try {
            $query = Db::table('notes')->select(
                'id',
                'title',
                'is_public',
                'created_at',
                'updated_at'
            );

            // 添加查询条件
            if (isset($conditions['user_id'])) {
                $query->where('user_id', $conditions['user_id']);
            }

            if (isset($conditions['keyword']) && $conditions['keyword']) {
                $query->where('title', 'LIKE', '%' . $conditions['keyword'] . '%')
                    ->orWhere('content', 'LIKE', '%' . $conditions['keyword'] . '%');
            }

            // 分页查询
            $total = $query->count();
            $items = $query->orderBy('updated_at', 'DESC')
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
        } catch (Exception $e) {
            $this->logger->error('查询笔记列表失败: ' . $e->getMessage(), ['conditions' => $conditions]);
            throw $e;
        }
    }

    /**
     * 根据ID和用户ID查询笔记.
     * @param int $id 笔记ID
     * @param int $userId 用户ID
     * @return null|array 笔记信息
     */
    public function findByIdAndUserId(int $id, int $userId): ?array
    {
        try {
            $note = Db::table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->first();

            return $note ? (array) $note : null;
        } catch (Exception $e) {
            $this->logger->error('查询笔记详情失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 创建笔记并保存第一个版本.
     * @param array $data 笔记数据
     * @return array 创建的笔记信息
     */
    public function createWithFirstVersion(array $data): array
    {
        return Db::transaction(function () use ($data) {
            // 创建笔记
            $noteId = Db::table('notes')->insertGetId($data);

            // 获取创建的笔记
            $note = Db::table('notes')->where('id', $noteId)->first();

            // 保存第一个版本
            $versionData = [
                'note_id' => $noteId,
                'title' => $data['title'],
                'content' => $data['content'],
                'version_number' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            Db::table('note_versions')->insert($versionData);

            return (array) $note;
        });
    }

    /**
     * 更新笔记并创建新版本.
     * @param int $id 笔记ID
     * @param int $userId 用户ID
     * @param array $data 更新数据
     * @return array 更新后的笔记信息
     */
    public function updateWithNewVersion(int $id, int $userId, array $data): array
    {
        return Db::transaction(function () use ($id, $userId, $data) {
            // 获取当前笔记
            $currentNote = Db::table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (! $currentNote) {
                throw new RuntimeException('笔记不存在或无权限操作');
            }

            // 更新笔记
            Db::table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->update($data);

            // 获取更新后的笔记
            $updatedNote = Db::table('notes')->where('id', $id)->first();

            // 确定是否需要创建新版本（内容有变化）
            $titleChanged = isset($data['title']) && $data['title'] !== $currentNote->title;
            $contentChanged = isset($data['content']) && $data['content'] !== $currentNote->content;

            if ($titleChanged || $contentChanged) {
                // 获取最新版本号
                $latestVersion = Db::table('note_versions')
                    ->where('note_id', $id)
                    ->max('version_number');

                // 准备版本数据
                $versionData = [
                    'note_id' => $id,
                    'title' => $updatedNote->title,
                    'content' => $updatedNote->content,
                    'version_number' => ($latestVersion ? $latestVersion + 1 : 1),
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                // 创建新版本
                Db::table('note_versions')->insert($versionData);
            }

            return (array) $updatedNote;
        });
    }

    /**
     * 删除笔记及所有版本.
     * @param int $id 笔记ID
     * @param int $userId 用户ID
     * @return bool 删除结果
     */
    public function delete(int $id, int $userId): bool
    {
        return Db::transaction(function () use ($id, $userId) {
            // 检查笔记是否存在且属于当前用户
            $noteExists = Db::table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->count() > 0;

            if (! $noteExists) {
                return false;
            }

            // 删除相关的版本记录
            Db::table('note_versions')->where('note_id', $id)->delete();

            // 删除笔记
            return Db::table('notes')
                ->where('id', $id)
                ->where('user_id', $userId)
                ->delete() > 0;
        });
    }

    /**
     * 获取笔记的所有版本.
     * @param int $noteId 笔记ID
     * @return array 版本列表
     */
    public function findVersionsByNoteId(int $noteId): array
    {
        try {
            return Db::table('note_versions')
                ->where('note_id', $noteId)
                ->select('id', 'version_number', 'created_at')
                ->orderBy('version_number', 'DESC')
                ->get()->toArray();
        } catch (Exception $e) {
            $this->logger->error('获取笔记版本列表失败: ' . $e->getMessage(), ['note_id' => $noteId]);
            throw $e;
        }
    }

    /**
     * 根据版本ID获取版本内容.
     * @param int $noteId 笔记ID
     * @param int $versionId 版本ID
     * @return null|array 版本内容
     */
    public function findVersionById(int $noteId, int $versionId): ?array
    {
        try {
            $version = Db::table('note_versions')
                ->where('id', $versionId)
                ->where('note_id', $noteId)
                ->first();

            return $version ? (array) $version : null;
        } catch (Exception $e) {
            $this->logger->error('获取版本内容失败: ' . $e->getMessage(), ['note_id' => $noteId, 'version_id' => $versionId]);
            throw $e;
        }
    }
}
