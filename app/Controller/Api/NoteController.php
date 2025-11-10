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

namespace App\Controller\Api;

use App\Constants\StatusCode;
use App\Controller\AbstractController;
use App\Controller\Api\Validator\NoteValidator;
use App\Middleware\JwtAuthMiddleware;
use App\Service\NoteService;
use App\Traits\LogTrait;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\Validation\ValidationException;

/**
 * 笔记控制器.
 * @Controller(prefix="/api/notes")
 * @Middleware(JwtAuthMiddleware::class)
 */
class NoteController extends AbstractController
{
    use LogTrait;

    /**
     * @Inject
     * @var NoteService
     */
    protected $noteService;

    /**
     * @Inject
     * @var NoteValidator
     */
    protected $validator;

    /**
     * 获取笔记列表.
     * @RequestMapping(path="", methods={"GET"})
     */
    public function index()
    {
        try {
            $params = $this->request->all();
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 验证参数
            try {
                $validatedData = $this->validator->validateNoteList($params);
            } catch (ValidationException $e) {
                return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
            }

            // 设置默认值
            $params['page'] = $params['page'] ?? 1;
            $params['per_page'] = $params['per_page'] ?? 10;

            $result = $this->noteService->getNotes($userId, $params);

            return $this->success($result, '获取成功');
        } catch (Exception $e) {
            $this->logError('获取笔记列表失败', ['error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取笔记列表失败');
        }
    }

    /**
     * 获取笔记详情.
     * @RequestMapping(path="/{id}", methods={"GET"})
     * @param mixed $id
     */
    public function show($id)
    {
        try {
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 验证参数
            try {
                $validatedData = $this->validator->validateNoteId(['id' => $id]);
                $id = $validatedData['id'];
            } catch (ValidationException $e) {
                return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
            }

            $note = $this->noteService->getNoteById($id, $userId);

            if (! $note) {
                return $this->fail(StatusCode::NOT_FOUND, '笔记不存在');
            }

            return $this->success($note, '获取成功');
        } catch (Exception $e) {
            $this->logError('获取笔记详情失败', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取笔记详情失败');
        }
    }

    /**
     * 创建笔记.
     * @RequestMapping(path="", methods={"POST"})
     */
    public function store()
    {
        try {
            $params = $this->request->all();
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 验证参数
            try {
                $validatedData = $this->validator->validateCreateNote($params);
            } catch (ValidationException $e) {
                return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
            }

            // 使用验证后的数据创建笔记
            $note = $this->noteService->createNote($userId, $validatedData);

            return $this->success($note, '创建成功', 201);
        } catch (Exception $e) {
            $this->logError('创建笔记失败', ['error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '创建笔记失败');
        }
    }

    /**
     * 更新笔记.
     * @RequestMapping(path="/{id}", methods={"PUT"})
     * @param mixed $id
     */
    public function update($id)
    {
        try {
            $params = $this->request->all();
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 验证参数
            try {
                $validatedData = $this->validator->validateUpdateNote($params);
            } catch (ValidationException $e) {
                return $this->fail(StatusCode::BAD_REQUEST, $e->validator->errors()->first());
            }

            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (! $existingNote) {
                return $this->fail(StatusCode::NOT_FOUND, '笔记不存在或无权限操作');
            }

            // 使用验证后的数据更新笔记
            $note = $this->noteService->updateNote($id, $userId, $validatedData);

            return $this->success($note, '更新成功');
        } catch (Exception $e) {
            $this->logError('更新笔记失败', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '更新笔记失败');
        }
    }

    /**
     * 删除笔记.
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     * @param mixed $id
     */
    public function destroy($id)
    {
        try {
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (! $existingNote) {
                return $this->fail(StatusCode::NOT_FOUND, '笔记不存在或无权限操作');
            }

            $result = $this->noteService->deleteNote($id, $userId);

            return $this->success(null, '删除成功');
        } catch (Exception $e) {
            $this->logError('删除笔记失败', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '删除笔记失败');
        }
    }

    /**
     * 获取笔记版本历史.
     * @RequestMapping(path="/{id}/versions", methods={"GET"})
     * @param mixed $id
     */
    public function getVersions($id)
    {
        try {
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (! $existingNote) {
                return $this->fail(StatusCode::NOT_FOUND, '笔记不存在或无权限操作');
            }

            $versions = $this->noteService->getNoteVersions($id);

            return $this->success($versions, '获取成功');
        } catch (Exception $e) {
            $this->logError('获取版本历史失败', ['id' => $id, 'error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取版本历史失败');
        }
    }

    /**
     * 获取指定版本笔记内容.
     * @RequestMapping(path="/{id}/versions/{versionId}", methods={"GET"})
     * @param mixed $id
     * @param mixed $versionId
     */
    public function getVersion($id, $versionId)
    {
        try {
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (! $existingNote) {
                return $this->fail(StatusCode::NOT_FOUND, '笔记不存在或无权限操作');
            }

            $version = $this->noteService->getNoteVersionById($id, $versionId);

            if (! $version) {
                return $this->fail(StatusCode::NOT_FOUND, '版本不存在');
            }

            return $this->success($version, '获取成功');
        } catch (Exception $e) {
            $this->logError('获取版本详情失败', ['id' => $id, 'versionId' => $versionId, 'error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '获取版本详情失败');
        }
    }

    // 恢复版本功能已整合到restoreVersion方法中

    /**
     * 恢复到指定版本.
     * @RequestMapping(path="/{id}/versions/{versionId}/restore", methods={"POST"})
     * @param mixed $id
     * @param mixed $versionId
     */
    public function restoreVersion($id, $versionId)
    {
        try {
            // 从JWT中获取用户ID，JwtAuthMiddleware已将用户信息注入到控制器中
            $userId = $this->user->id ?? null;

            if (! $userId) {
                return $this->fail(StatusCode::UNAUTHORIZED, '用户未登录');
            }

            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (! $existingNote) {
                return $this->fail(StatusCode::NOT_FOUND, '笔记不存在或无权限操作');
            }

            // 检查版本是否存在
            $version = $this->noteService->getNoteVersionById($id, $versionId);
            if (! $version) {
                return $this->fail(StatusCode::NOT_FOUND, '版本不存在');
            }

            // 恢复版本
            $note = $this->noteService->restoreNoteFromVersion($id, $versionId, $userId);

            return $this->success($note, '恢复成功');
        } catch (Exception $e) {
            $this->logError('恢复版本失败', ['id' => $id, 'versionId' => $versionId, 'error' => $e->getMessage()]);
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '恢复版本失败');
        }
    }
}
