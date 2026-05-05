# ECUSTMC 统一身份认证

Blessing Skin Server 插件，支持华东理工大学三种认证方式。

## 快速开始

1. 把 `auth-ecust` 文件夹放到 `plugins/` 目录
2. 后台 → 插件管理 → 启用「ECUST 统一身份认证」
3. 登录页出现「通过 ECUST 统一身份认证登录」按钮，点击进入

## 三种认证方式

| 方式 | 说明 | 输入 |
|------|------|------|
| Eduroam | eduroam 服务器验证 | 学号/工号 |
| 官方认证 | AnyShare 网盘 API（RSA 加密） | 学号/工号 |
| 邮箱登录 | 企业邮箱 SMTP 验证 | 完整邮箱 |

## 项目结构

```
├── auth-ecust/          # 插件（用这个）
├── auth-eduroam/        # 参考的原始 eduroam 插件
├── ecust-auth/          # 官方认证 API 参考实现
├── archive/             # 旧版手动集成文件（已废弃）
└── README.md
```
