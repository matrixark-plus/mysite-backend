<?php

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Service\ContactService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;

/**
 * @Controller(prefix="/api/contact")
 */
class ContactController extends AbstractController
{
    /**
     * @Inject
     * @var ContactService
     */
    protected $contactService;
    
    /**
     * 提交联系表单
     * 
     * @RequestMapping(path="/submit", methods={"POST"})
     */
    public function submitContact(RequestInterface $request)
    {
        try {
            $data = $request->all();
            
            // 获取客户端IP
            $data['ip'] = $request->getServerParams()['remote_addr'] ?? '';
            
            // 提交联系表单
            $result = $this->contactService->submitContactForm($data);
            
            if ($result['success']) {
                return $this->success(null, $result['message']);
            } else {
                return $this->fail(400, $result['message']);
            }
        } catch (\Exception $e) {
            logger()->error('提交联系表单异常: ' . $e->getMessage());
            return $this->fail(500, '服务器内部错误');
        }
    }
}