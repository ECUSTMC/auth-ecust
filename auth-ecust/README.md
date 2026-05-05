# auth-ecust

华东理工大学统一身份认证插件，支持三种登录方式。

## 认证方式

| 方式 | 说明 | 输入格式 |
|------|------|----------|
| **Eduroam** | 通过 eduroam 服务器验证 | 学号/工号 |
| **官方认证** | 通过 AnyShare 网盘 API 验证（RSA 加密） | 学号/工号 |
| **邮箱登录** | 通过学校企业邮箱 SMTP 验证 | 完整邮箱地址 |

## 邮箱规则

- **5位工号** → `@ecust.edu.cn`（教职工）
- **其他学号** → `@mail.ecust.edu.cn`（学生）

## 安装

把 `auth-ecust` 文件夹放到 Blessing Skin 的 `plugins/` 目录，后台启用即可。

## 依赖

- Blessing Skin Server >= 6
- PHPMailer（邮箱登录需要，Blessing Skin 自带）

## 文件结构

```
auth-ecust/
├── bootstrap.php           # 插件入口
├── package.json            # 插件元信息
├── public_key.pem          # RSA 公钥
├── src/
│   ├── LoginController.php # 登录控制器（三种认证）
│   ├── EcustAuth.php       # 官方认证服务
│   └── Configuration.php   # 后台配置页
├── views/                  # Twig 模板
└── lang/                   # 语言包
```
