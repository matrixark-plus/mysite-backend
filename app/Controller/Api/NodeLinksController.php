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

use App\Controller\AbstractController;
use App\Controller\Api\Validator\NodeLinksValidator;
use App\Service\NodeLinksService;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\ValidationException;

/**
 * 节点链接控制器
 * 处理节点链接相关的API请求
 * @Controller
 */
class NodeLinksController extends AbstractController
{
    /**
     * @Inject
     * @var NodeLinksService
     */
    protected $nodeLinksService;

    /**
     * @Inject
     * @var NodeLinksValidator
     */
    protected $validator;

    /**
     * 创建节点链接.
     * @return \Psr\Http\Message\ResponseInterface
     * @RequestMapping(path="/api/node-links", methods={"POST"})
     */
    public function create(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request->all();
            $creatorId = $this->getCurrentUserId();

            // 验证用户ID和创建链接参数
            $this->validator->validateUserId($creatorId);
            $this->validator->validateCreateLink($data);

            $link = $this->nodeLinksService->createLink($data, $creatorId);

            return $this->success([
                'data' => $link,
                'message' => '创建节点链接成功',
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), []);
        }
    }

    /**
     * 批量创建节点链接.
     * @return \Psr\Http\Message\ResponseInterface
     * @RequestMapping(path="/api/node-links/batch", methods={"POST"})
     */
    public function batchCreate(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $data = $request->all();
            $linksData = $data['links'] ?? [];
            $creatorId = $this->getCurrentUserId();

            // 验证用户ID和批量创建链接数据
            $this->validator->validateUserId($creatorId);
            $this->validator->validateBatchCreateLinks($linksData);

            $results = $this->nodeLinksService->batchCreateLinks($linksData, $creatorId);

            return $this->success([
                'data' => $results,
                'message' => '批量创建节点链接成功',
                'created_count' => count($results),
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新节点链接.
     * @return \Psr\Http\Message\ResponseInterface
     * @RequestMapping(path="/api/node-links/{id}", methods={"PUT"})
     */
    public function update(RequestInterface $request, ResponseInterface $response, int $id)
    {
        try {
            $data = $request->all();
            $creatorId = $this->getCurrentUserId();

            // 验证链接ID、用户ID和更新参数
            $this->validator->validateLinkId($id);
            $this->validator->validateUserId($creatorId);
            $this->validator->validateUpdateLink($data);

            $success = $this->nodeLinksService->updateLink($id, $data, $creatorId);

            if ($success) {
                return $this->success([
                    'message' => '更新节点链接成功',
                ]);
            }
            return $this->error('更新节点链接失败');
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除节点链接.
     * @return \Psr\Http\Message\ResponseInterface
     * @RequestMapping(path="/api/node-links/{id}", methods={"DELETE"})
     */
    public function delete(RequestInterface $request, ResponseInterface $response, int $id)
    {
        try {
            $creatorId = $this->getCurrentUserId();

            // 验证链接ID和用户ID
            $this->validator->validateLinkId($id);
            $this->validator->validateUserId($creatorId);

            $success = $this->nodeLinksService->deleteLink($id, $creatorId);

            if ($success) {
                return $this->success([
                    'message' => '删除节点链接成功',
                ]);
            }
            return $this->error('删除节点链接失败');
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取脑图的所有链接.
     * @return \Psr\Http\Message\ResponseInterface
     * @RequestMapping(path="/api/mindmaps/{rootId}/node-links", methods={"GET"})
     */
    public function getLinksByRootId(RequestInterface $request, ResponseInterface $response, int $rootId)
    {
        try {
            // 验证脑图根节点ID
            $this->validator->validateRootId($rootId);

            $links = $this->nodeLinksService->getLinksByRootId($rootId);

            return $this->success([
                'data' => $links,
                'total' => count($links),
            ]);
        } catch (ValidationException $e) {
            return $this->validationError($e->getMessage(), []);
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取当前用户ID.
     */
    protected function getCurrentUserId(): int
    {
        // 这里应该从JWT token或session中获取用户ID
        // 暂时返回固定值，实际使用时需要根据认证机制调整
        return (int) 1;
    }
}
