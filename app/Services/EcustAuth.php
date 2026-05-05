<?php

namespace App\Services;

/**
 * 华东理工大学 AnyShare 网盘认证类
 * 用于验证用户是否为校内用户（官方认证接口）
 */
class EcustAuth
{
    private $baseUrl = 'https://pan.ecust.edu.cn/api/v1';
    private $publicKeyPath;

    public function __construct($publicKeyPath = null)
    {
        $this->publicKeyPath = $publicKeyPath ?: storage_path('app/public_key.pem');
    }

    /**
     * 获取服务器配置
     * @return array 配置信息
     */
    public function getConfig()
    {
        $url = $this->baseUrl . '/auth1/getconfig';
        return $this->post($url, []);
    }

    /**
     * 用户登录认证
     * @param string $account 学号/工号
     * @param string $password 密码（明文）
     * @return array 认证结果，包含 tokenid, userid, expires 等
     */
    public function login($account, $password)
    {
        // 1. 加密密码
        $encryptedPassword = $this->encryptPassword($password);

        // 2. 构建请求体
        $postData = [
            'account' => $account,
            'password' => $encryptedPassword,
            'deviceinfo' => [
                'ostype' => 6,  // 6 表示 Web 端
                'name' => 'ECUST Auth PHP',
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

        // 3. 发送登录请求
        $url = $this->baseUrl . '/auth1/getnew';
        $result = $this->post($url, $postData);

        return $result;
    }

    /**
     * 验证用户是否为校内用户
     * @param string $account 学号/工号
     * @param string $password 密码
     * @return array ['valid' => bool, 'message' => string, 'data' => array|null]
     */
    public function verifyUser($account, $password)
    {
        try {
            $result = $this->login($account, $password);

            // 检查是否登录成功
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

            // 登录失败
            $errmsg = $result['errmsg'] ?? $result['error'] ?? $result['hint'] ?? '用户名或密码错误';
            return [
                'valid' => false,
                'message' => '认证失败：' . $errmsg,
                'data' => [
                    'errcode' => $result['errcode'] ?? null
                ]
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
     * 使用 RSA 公钥加密密码
     * @param string $password 明文密码
     * @return string base64 编码的加密密码
     */
    private function encryptPassword($password)
    {
        // 检查公钥文件是否存在
        if (!file_exists($this->publicKeyPath)) {
            throw new \RuntimeException('公钥文件不存在: ' . $this->publicKeyPath);
        }

        // 读取公钥
        $publicKey = file_get_contents($this->publicKeyPath);
        if (!$publicKey) {
            throw new \RuntimeException('无法读取公钥文件: ' . $this->publicKeyPath);
        }

        // 加载公钥
        $key = openssl_pkey_get_public($publicKey);
        if (!$key) {
            throw new \RuntimeException('无效的公钥格式');
        }

        // RSA 加密 (RSA_PKCS1_PADDING)
        $encrypted = '';
        $success = openssl_public_encrypt($password, $encrypted, $key, OPENSSL_PKCS1_PADDING);

        if (!$success) {
            throw new \RuntimeException('密码加密失败: ' . openssl_error_string());
        }

        // Base64 编码
        return base64_encode($encrypted);
    }

    /**
     * 发送 POST 请求
     * @param string $url 请求地址
     * @param array $data 请求数据
     * @return array 响应数据
     */
    private function post($url, $data)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
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
        ]);

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
