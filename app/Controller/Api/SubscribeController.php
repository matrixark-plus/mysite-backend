<?php

namespace App\Controller\Api;

use App\Controller\AbstractController;
use App\Service\SubscribeService;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;

/**
 * @Controller(prefix="/api/subscribe")
 */
class SubscribeController extends AbstractController
{
    /**
     * @Inject
     * @var SubscribeService
     */
    protected $subscribeService;
    
    /**
     * 订阅博客
     * 
     * @RequestMapping(path="/blog", methods={"POST"})
     */
    public function blogSubscribe(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $email = $request->input('email');
            
            // 验证邮箱格式
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->fail(400, '邮箱格式不正确');
            }
            
            // 添加订阅
            $result = $this->subscribeService->addBlogSubscribe($email);
            
            if ($result['success']) {
                return $this->success(null, $result['message']);
            } else {
                return $this->fail(400, $result['message']);
            }
        } catch (\Exception $e) {
            logger()->error('订阅博客异常: ' . $e->getMessage());
            return $this->fail(500, '服务器内部错误');
        }
    }
    
    /**
     * 确认订阅
     * 
     * @RequestMapping(path="/confirm", methods={"GET"})
     */
    public function confirmSubscribe(RequestInterface $request, ResponseInterface $response)
    {
        try {
            $token = $request->input('token');
            
            if (!$token) {
                return $this->fail(400, '缺少确认参数');
            }
            
            // 确认订阅
            $result = $this->subscribeService->confirmSubscribe($token);
            
            // 直接返回HTML页面，让用户在浏览器中看到确认结果
            $html = $this->buildConfirmPage($result['success'], $result['message']);
            return $response->raw($html)->withHeader('Content-Type', 'text/html');
        } catch (\Exception $e) {
            logger()->error('确认订阅异常: ' . $e->getMessage());
            $html = $this->buildConfirmPage(false, '服务器内部错误，请稍后重试');
            return $response->raw($html)->withHeader('Content-Type', 'text/html');
        }
    }
    
    /**
     * 构建确认页面
     * 
     * @param bool $success 是否成功
     * @param string $message 消息
     * @return string HTML内容
     */
    protected function buildConfirmPage($success, $message)
    {
        $title = $success ? '订阅成功' : '订阅失败';
        $color = $success ? '#00ff99' : '#ff6600';
        
        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
            <style>
                body {
                    font-family: 'Microsoft YaHei', Arial, sans-serif;
                    background-color: #1a1a1a;
                    color: #ffffff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                    text-align: center;
                }
                .container {
                    max-width: 600px;
                    padding: 40px;
                    border-radius: 8px;
                    background-color: #2a2a2a;
                }
                h1 {
                    color: {$color};
                    font-size: 32px;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 18px;
                    line-height: 1.6;
                }
                .btn {
                    display: inline-block;
                    margin-top: 30px;
                    padding: 12px 30px;
                    background-color: #003366;
                    color: #ffffff;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 16px;
                    transition: background-color 0.3s;
                }
                .btn:hover {
                    background-color: #004080;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>{$title}</h1>
                <p>{$message}</p>
                <a href="/" class="btn">返回网站首页</a>
            </div>
        </body>
        </html>
        HTML;
        
        return $html;
    }
}