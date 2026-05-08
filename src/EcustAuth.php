<?php

namespace AuthEcust;

/**
 * 华东理工大学 AnyShare 网盘认证类
 * 用于验证用户是否为校内用户（官方认证接口）
 */
class EcustAuth
{
    private $baseUrl = 'https://pan.ecust.edu.cn/api/v1';
    private $publicKeyPath;
    private $proxy;

    public function __construct($publicKeyPath = null, $proxy = null)
    {
        $this->publicKeyPath = $publicKeyPath ?: __DIR__ . '/../public_key.pem';
        $this->proxy = $proxy;
    }

    /**
     * 获取服务器配置
     */
    public function getConfig()
    {
        $url = $this->baseUrl . '/auth1/getconfig';
        return $this->post($url, []);
    }

    /**
     * 用户登录认证
     */
    public function login($account, $password)
    {
        $encryptedPassword = $this->encryptPassword($password);

        $postData = [
            'account' => $account,
            'password' => $encryptedPassword,
            'deviceinfo' => [
                'ostype' => 6,
                'name' => 'ECUST Auth Plugin',
                'devicetype' => 'Web'
            ],
            'vcodeinfo' => [
                'uuid' => '',
                'vcode' => '',
                'ismodify' => false
            ],
            'dualfactorauthinfo' => [
                'validcode' => ['vcode' => ''],
                'OTP' => ['OTP' => '']
            ]
        ];

        $url = $this->baseUrl . '/auth1/getnew';
        return $this->post($url, $postData);
    }

    /**
     * 验证用户是否为校内用户
     */
    public function verifyUser($account, $password)
    {
        try {
            $result = $this->login($account, $password);

            if (isset($result['tokenid']) && isset($result['userid'])) {
                return [
                    'valid' => true,
                    'message' => '认证成功，确认为校内用户',
                    'data' => [
                        'userid' => $result['userid'],
                        'tokenid' => $result['tokenid'],
                        'expires' => $result['expires'] ?? 3600,
                        'needmodifypassword' => $result['needmodifypassword'] ?? false
                    ]
                ];
            }

            $errmsg = $result['errmsg'] ?? $result['error'] ?? $result['hint'] ?? '用户名或密码错误';
            return [
                'valid' => false,
                'message' => '认证失败：' . $errmsg,
                'data' => ['errcode' => $result['errcode'] ?? null]
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => '认证异常：' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * RSA 公钥加密密码
     */
    private function encryptPassword($password)
    {
        if (!file_exists($this->publicKeyPath)) {
            throw new \RuntimeException('公钥文件不存在: ' . $this->publicKeyPath);
        }

        $publicKey = file_get_contents($this->publicKeyPath);
        if (!$publicKey) {
            throw new \RuntimeException('无法读取公钥文件');
        }

        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            throw new \RuntimeException('无效的公钥格式');
        }

        $encrypted = '';
        $success = openssl_public_encrypt($password, $encrypted, $key, OPENSSL_PKCS1_PADDING);
        if (!$success) {
            throw new \RuntimeException('密码加密失败: ' . openssl_error_string());
        }

        return base64_encode($encrypted);
    }

    /**
     * 发送 POST 请求
     */
    private function post($url, $data)
    {
        $ch = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30
        ];

        if ($this->proxy) {
            $options[CURLOPT_PROXY] = $this->proxy;
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException('请求失败: ' . $error);
        }

        $result = json_decode($response, true);
        if ($httpCode >= 500) {
            $errorMsg = $result['error'] ?? $result['hint'] ?? 'HTTP Error: ' . $httpCode;
            throw new \RuntimeException($errorMsg);
        }

        return $result ?: [];
    }
}
