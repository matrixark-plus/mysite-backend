<?php

namespace App\Service;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Redis\RedisFactory;
use Hyperf\Config\ConfigInterface;

class VerifyCodeService
{
    /**
     * @var \Redis
     */
    protected $redis;
    
    /**
     * @var ConfigInterface
     */
    protected $config;
    
    /**
     * @var MailService
     */
    protected $mailService;
    
    public function __construct()
    {
        $container = ApplicationContext::getContainer();
        $this->redis = $container->get(RedisFactory::class)->get('default');
        $this->config = $container->get(ConfigInterface::class);
        $this->mailService = $container->get(MailService::class);
    }
    
    /**
     * 生成验证码
     * 
     * @param int $length 验证码长度
     * @return string
     */
    public function generateCode($length = 6)
    {
        $length = $length ?: $this->config->get('email.verify_code.length', 6);
        $chars = '0123456789';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return $code;
    }
    
    /**
     * 发送验证码到邮箱
     * 
     * @param string $email 邮箱地址
     * @return array 结果数组
     */
    public function sendEmailCode($email)
    {
        // 检查发送频率
        $key = $this->getEmailCodeKey($email);
        $lastSendTime = $this->redis->get($key . ':last_send');
        
        if ($lastSendTime && (time() - $lastSendTime < 60)) {
            return [
                'success' => false,
                'message' => '验证码发送过于频繁，请稍后再试'
            ];
        }
        
        // 生成验证码
        $code = $this->generateCode();
        $expireMinutes = $this->config->get('email.verify_code.expire_minutes', 10);
        
        // 存储验证码到Redis
        $this->redis->setex($key, $expireMinutes * 60, $code);
        $this->redis->setex($key . ':last_send', 60, time());
        
        // 发送邮件
        $result = $this->mailService->sendVerifyCode($email, $code, $expireMinutes);
        
        if ($result) {
            return [
                'success' => true,
                'message' => '验证码已发送，请注意查收'
            ];
        } else {
            return [
                'success' => false,
                'message' => '验证码发送失败，请稍后重试'
            ];
        }
    }
    
    /**
     * 验证邮箱验证码
     * 
     * @param string $email 邮箱地址
     * @param string $code 验证码
     * @return array 验证结果
     */
    public function verifyEmailCode($email, $code)
    {
        $key = $this->getEmailCodeKey($email);
        $storedCode = $this->redis->get($key);
        
        if (!$storedCode) {
            return [
                'success' => false,
                'message' => '验证码已过期'
            ];
        }
        
        if ($storedCode !== $code) {
            return [
                'success' => false,
                'message' => '验证码错误'
            ];
        }
        
        // 验证成功后删除验证码
        $this->redis->del($key);
        
        return [
            'success' => true,
            'message' => '验证成功'
        ];
    }
    
    /**
     * 获取验证码的Redis键名
     * 
     * @param string $email 邮箱地址
     * @return string
     */
    protected function getEmailCodeKey($email)
    {
        return 'verify_code:email:' . md5($email);
    }
}