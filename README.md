# auth-ecust

华东理工大学统一身份认证插件，支持三种登录方式。

## 安装

把本仓库克隆到 Blessing Skin 的 `plugins/auth-ecust` 目录，后台启用即可。

```bash
cd plugins
git clone <仓库地址> auth-ecust
```

## 认证方式

| 方式 | 说明 | 输入格式 |
|------|------|----------|
| **官方认证**（默认） | AnyShare 网盘 API，RSA 加密密码 | 学号/工号 |
| **邮箱登录** | 企业邮箱 SMTP 验证 | 完整邮箱 |
| **Eduroam**（不推荐） | eduroam 服务器验证 | 学号/工号 |

## 邮箱规则

- **5位工号** → `@ecust.edu.cn`（教职工）
- **其他学号** → `@mail.ecust.edu.cn`（学生）

## 依赖

- Blessing Skin Server >= 6
- PHPMailer（Blessing Skin 自带）

## 文件结构

```
├── bootstrap.php           # 插件入口
├── package.json            # 插件元信息
├── public_key.pem          # RSA 公钥
├── src/
│   ├── LoginController.php # 登录控制器
│   ├── EcustAuth.php       # 官方认证服务
│   └── Configuration.php   # 后台配置页
├── views/                  # Twig 模板
└── lang/                   # 语言包
```

## 许可证

本项目基于 [MIT License](LICENSE) 开源。
