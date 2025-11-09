<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Controller\Api\Validator\NoteValidator;
use App\Service\NoteService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Utils\Context;
use App\Middleware\JwtAuthMiddleware;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\Validation\ValidationException;

/**
 * 笔记控制器
 * @Controller(prefix="/api/notes")
 * @Middleware(JwtAuthMiddleware::class)
 */
class NoteController extends AbstractController
{
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
     * 获取笔记列表
     * @RequestMapping(path="", methods={"GET"})
     */
    public function index(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $userId = Context::get('user_id');
            
            // 验证参数
            try {
                $validatedData = $this->validator->validateNoteList($params);
            } catch (ValidationException $e) {
                return $this->validationError('参数验证失败', $e->validator->errors()->toArray());
            }

            // 设置默认值
            $params['page'] = $params['page'] ?? 1;
            $params['per_page'] = $params['per_page'] ?? 10;
            
            $result = $this->noteService->getNotes($userId, $params);
            
            return $response->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '获取笔记列表失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 获取笔记详情
     * @RequestMapping(path="/{id}", methods={"GET"})
     */
    public function show($id, ResponseInterface $response)
    {
        try {
            $userId = Context::get('user_id');
            
            $note = $this->noteService->getNoteById($id, $userId);
            
            if (!$note) {
                return $response->json([
                    'code' => 404,
                    'message' => '笔记不存在',
                    'data' => [],
                ]);
            }
            
            return $response->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $note,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '获取笔记详情失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 创建笔记
     * @RequestMapping(path="", methods={"POST"})
     */
    public function store(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $userId = Context::get('user_id');
            
            // 验证参数
            $validator = $this->validatorFactory->make($params, [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'is_public' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return $response->json([
                    'code' => 400,
                    'message' => '参数验证失败',
                    'data' => $validator->errors()->toArray(),
                ]);
            }
            
            $note = $this->noteService->createNote($userId, $params);
            
            return $response->json([
                'code' => 201,
                'message' => '创建成功',
                'data' => $note,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '创建笔记失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 更新笔记
     * @RequestMapping(path="/{id}", methods={"PUT"})
     */
    public function update($id, RequestInterface $request, ResponseInterface $response)
    {
        try {
            $params = $request->all();
            $userId = Context::get('user_id');
            
            // 验证参数
            try {
                $validatedData = $this->validator->validateUpdateNote($params);
            } catch (ValidationException $e) {
                return $this->validationError('参数验证失败', $e->validator->errors()->toArray());
            }
            
            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (!$existingNote) {
                return $response->json([
                    'code' => 404,
                    'message' => '笔记不存在或无权限操作',
                    'data' => [],
                ]);
            }
            
            $note = $this->noteService->updateNote($id, $userId, $params);
            
            return $response->json([
                'code' => 200,
                'message' => '更新成功',
                'data' => $note,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '更新笔记失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 删除笔记
     * @RequestMapping(path="/{id}", methods={"DELETE"})
     */
    public function destroy($id, ResponseInterface $response)
    {
        try {
            $userId = Context::get('user_id');
            
            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (!$existingNote) {
                return $response->json([
                    'code' => 404,
                    'message' => '笔记不存在或无权限操作',
                    'data' => [],
                ]);
            }
            
            $this->noteService->deleteNote($id, $userId);
            
            return $response->json([
                'code' => 200,
                'message' => '删除成功',
                'data' => [],
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '删除笔记失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 获取笔记版本历史
     * @RequestMapping(path="/{id}/versions", methods={"GET"})
     */
    public function getVersions($id, ResponseInterface $response)
    {
        try {
            $userId = Context::get('user_id');
            
            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (!$existingNote) {
                return $response->json([
                    'code' => 404,
                    'message' => '笔记不存在或无权限操作',
                    'data' => [],
                ]);
            }
            
            $versions = $this->noteService->getNoteVersions($id);
            
            return $response->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $versions,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '获取版本历史失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }

    /**
     * 获取指定版本笔记内容
     * @RequestMapping(path="/{id}/versions/{versionId}", methods={"GET"})
     */
    public function getVersion($id, $versionId, ResponseInterface $response)
    {
        try {
            $userId = Context::get('user_id');
            
            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (!$existingNote) {
                return $response->json([
                    'code' => 404,
                    'message' => '笔记不存在或无权限操作',
                    'data' => [],
                ]);
            }
            
            $version = $this->noteService->getNoteVersionById($id, $versionId);
            
            if (!$version) {
                return $response->json([
                    'code' => 404,
                    'message' => '版本不存在',
                    'data' => [],
                ]);
            }
            
            return $response->json([
                'code' => 200,
                'message' => '获取成功',
                'data' => $version,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '恢复版本失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }
    
    // 恢复版本功能已整合到restoreVersion方法中

    /**
     * 恢复到指定版本
     * @RequestMapping(path="/{id}/versions/{versionId}/restore", methods={"POST"})
     */
    public function restoreVersion($id, $versionId, ResponseInterface $response)
    {
        try {
            $userId = Context::get('user_id');
            
            // 检查笔记是否存在且属于当前用户
            $existingNote = $this->noteService->getNoteById($id, $userId);
            if (!$existingNote) {
                return $response->json([
                    'code' => 404,
                    'message' => '笔记不存在或无权限操作',
                    'data' => [],
                ]);
            }
            
            $note = $this->noteService->restoreNoteFromVersion($id, $versionId, $userId);
            
            if (!$note) {
                return $response->json([
                    'code' => 404,
                    'message' => '版本不存在',
                    'data' => [],
                ]);
            }
            
            return $response->json([
                'code' => 200,
                'message' => '恢复成功',
                'data' => $note,
            ]);
        } catch (\Exception $e) {
            return $response->json([
                'code' => 500,
                'message' => '恢复版本失败',
                'data' => ['error' => $e->getMessage()],
            ]);
        }
    }
}