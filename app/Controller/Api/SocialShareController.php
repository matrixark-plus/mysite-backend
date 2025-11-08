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
use App\Service\SocialShareService;
use Exception;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;

/**
 * @Controller(prefix="/api/social")
 */
class SocialShareController extends AbstractController
{
    /**
     * @Inject
     * @var SocialShareService
     */
    protected $socialShareService;

    /**
     * 获取分享配置.
     *
     * @RequestMapping(path="/share/config", methods={"GET"})
     */
    public function getShareConfig()
    {
        try {
            $config = $this->socialShareService->getShareConfig();
            return $this->success($config);
        } catch (Exception $e) {
            logger()->error('获取分享配置异常: ' . $e->getMessage());
            return $this->fail(500, '服务器内部错误');
        }
    }
}
