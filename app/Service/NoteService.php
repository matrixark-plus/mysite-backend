<?php

namespace App\Service;

use App\Repository\NoteRepository;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

/**
 * 笔记服务类
 */
class NoteService
{
    /**
     * @Inject
     * @var NoteRepository
     */
    protected $noteRepository;

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
     * 获取用户笔记列表
     * @param int $userId 用户ID
     * @param array $params 查询参数
     * @return array 分页结果
     */
    public function getNotes(int $userId, array $params): array
    {
        try {
            $page = $params['page'] ?? 1;
            $perPage = $params['per_page'] ?? 10;
            $keyword = $params['keyword'] ?? '';
            
            $query = [
                'user_id' => $userId,
                'keyword' => $keyword,
            ];
            
            $notes = $this->noteRepository->findAllBy($query, $page, $perPage);
            
            return $notes;
        } catch (\Exception $e) {
            $this->logger->error('获取笔记列表失败: ' . $e->getMessage(), ['user_id' => $userId, 'params' => $params]);
            throw $e;
        }
    }

    /**
     * 根据ID获取笔记
     * @param int $id 笔记ID
     * @param int $userId 用户ID
     * @return array|null 笔记信息
     */
    public function getNoteById(int $id, int $userId): ?array
    {
        try {
            return $this->noteRepository->findByIdAndUserId($id, $userId);
        } catch (\Exception $e) {
            $this->logger->error('获取笔记详情失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 创建笔记
     * @param int $userId 用户ID
     * @param array $data 笔记数据
     * @return array 创建的笔记信息
     */
    public function createNote(int $userId, array $data): array
    {
        try {
            // 验证数据
            $this->validateNoteData($data);
            
            // 设置默认值
            $data['user_id'] = $userId;
            $data['is_public'] = $data['is_public'] ?? false;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // 创建笔记并保存第一个版本
            $note = $this->noteRepository->createWithFirstVersion($data);
            
            $this->logger->info('创建笔记成功', ['id' => $note['id'], 'user_id' => $userId]);
            
            return $note;
        } catch (\Exception $e) {
            $this->logger->error('创建笔记失败: ' . $e->getMessage(), ['user_id' => $userId, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * 更新笔记
     * @param int $id 笔记ID
     * @param int $userId 用户ID
     * @param array $data 更新数据
     * @return array 更新后的笔记信息
     */
    public function updateNote(int $id, int $userId, array $data): array
    {
        try {
            // 验证数据
            if (!empty($data)) {
                $this->validateNoteData($data, false);
            }
            
            // 设置更新时间
            $data['updated_at'] = date('Y-m-d H:i:s');
            
            // 更新笔记并创建新版本
            $note = $this->noteRepository->updateWithNewVersion($id, $userId, $data);
            
            $this->logger->info('更新笔记成功', ['id' => $id, 'user_id' => $userId]);
            
            return $note;
        } catch (\Exception $e) {
            $this->logger->error('更新笔记失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId, 'data' => $data]);
            throw $e;
        }
    }

    /**
     * 删除笔记
     * @param int $id 笔记ID
     * @param int $userId 用户ID
     * @return bool 删除结果
     */
    public function deleteNote(int $id, int $userId): bool
    {
        try {
            $result = $this->noteRepository->delete($id, $userId);
            
            if ($result) {
                $this->logger->info('删除笔记成功', ['id' => $id, 'user_id' => $userId]);
            }
            
            return $result;
        } catch (\Exception $e) {
            $this->logger->error('删除笔记失败: ' . $e->getMessage(), ['id' => $id, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 获取笔记版本历史
     * @param int $noteId 笔记ID
     * @return array 版本列表
     */
    public function getNoteVersions(int $noteId): array
    {
        try {
            return $this->noteRepository->findVersionsByNoteId($noteId);
        } catch (\Exception $e) {
            $this->logger->error('获取笔记版本历史失败: ' . $e->getMessage(), ['note_id' => $noteId]);
            throw $e;
        }
    }

    /**
     * 获取指定版本笔记内容
     * @param int $noteId 笔记ID
     * @param int $versionId 版本ID
     * @return array|null 版本内容
     */
    public function getNoteVersionById(int $noteId, int $versionId): ?array
    {
        try {
            return $this->noteRepository->findVersionById($noteId, $versionId);
        } catch (\Exception $e) {
            $this->logger->error('获取指定版本笔记内容失败: ' . $e->getMessage(), ['note_id' => $noteId, 'version_id' => $versionId]);
            throw $e;
        }
    }

    /**
     * 从指定版本恢复笔记
     * @param int $noteId 笔记ID
     * @param int $versionId 版本ID
     * @param int $userId 用户ID
     * @return array|null 恢复后的笔记信息
     */
    public function restoreNoteFromVersion(int $noteId, int $versionId, int $userId): ?array
    {
        try {
            // 获取指定版本
            $version = $this->noteRepository->findVersionById($noteId, $versionId);
            
            if (!$version) {
                return null;
            }
            
            // 准备恢复数据
            $restoreData = [
                'title' => $version['title'],
                'content' => $version['content'],
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            // 恢复笔记并创建新版本
            $note = $this->noteRepository->updateWithNewVersion($noteId, $userId, $restoreData);
            
            $this->logger->info('从版本恢复笔记成功', ['note_id' => $noteId, 'version_id' => $versionId, 'user_id' => $userId]);
            
            return $note;
        } catch (\Exception $e) {
            $this->logger->error('从版本恢复笔记失败: ' . $e->getMessage(), ['note_id' => $noteId, 'version_id' => $versionId, 'user_id' => $userId]);
            throw $e;
        }
    }

    /**
     * 验证笔记数据
     * @param array $data 笔记数据
     * @param bool $requireAll 是否所有字段都必填
     */
    protected function validateNoteData(array $data, bool $requireAll = true): void
    {
        // 验证标题
        if (isset($data['title']) || $requireAll) {
            if (empty($data['title']) && $requireAll) {
                throw new \InvalidArgumentException('笔记标题不能为空');
            }
            if (isset($data['title']) && mb_strlen($data['title']) > 255) {
                throw new \InvalidArgumentException('笔记标题不能超过255个字符');
            }
        }
        
        // 验证内容
        if (isset($data['content']) || $requireAll) {
            if (empty($data['content']) && $requireAll) {
                throw new \InvalidArgumentException('笔记内容不能为空');
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