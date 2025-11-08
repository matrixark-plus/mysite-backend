<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Service\ContactService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use App\Constants\StatusCode;
use App\Traits\LogTrait;

/**
 * @Controller(prefix="/api/contact")
 */
class ContactController extends AbstractController
{
    use LogTrait;
    
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
            $data['ip'] = $request->getServerParams()["remote_addr"] ?? '';
            
            // 提交联系表单
            $result = $this->contactService->submitContactForm($data);
            
            if ($result['success']) {
                return $this->success(null, $result['message']);
            } else {
                return $this->fail(StatusCode::BAD_REQUEST, $result['message']);
            }
        } catch (\Exception $e) {
            $this->logError('提交联系表单异常', ['message' => $e->getMessage()], $e, 'contact');
            return $this->fail(StatusCode::INTERNAL_SERVER_ERROR, '服务器内部错误');
        }
    }
}