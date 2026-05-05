# ECUSTMC 统一身份认证登录

支持两种认证方式：**Eduroam** 和 **官方认证（AnyShare 网盘 API）**，用户可在登录页自由切换。

---

## 文件说明

| 文件 | 作用 | 放到哪里 |
|------|------|----------|
| `LoginController.php` | 登录/注册逻辑，支持两种认证 | `app/Http/Controllers/` |
| `LoginController_smtp.php` | SMTP 邮箱登录（可选） | `app/Http/Controllers/` |
| `app/Services/EcustAuth.php` | 官方认证服务类（RSA 加密） | `app/Services/` |
| `storage/app/public_key.pem` | RSA 公钥，官方认证加密用 | `storage/app/` |
| `eduroamlogin.blade.php` | 登录页面视图 | `resources/views/` |
| `smtplogin.blade.php` | SMTP 登录页面视图（可选） | `resources/views/` |
| `web.php` | 路由配置（参考用，合并到你自己的路由文件） | `routes/` |

---

## 部署步骤

### 1. 复制文件到对应目录

```
你的Laravel项目/
├── app/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── LoginController.php        ← 复制过去
│   │       └── LoginController_smtp.php   ← 复制过去（可选）
│   └── Services/
│       └── EcustAuth.php                  ← 复制过去
├── storage/
│   └── app/
│       └── public_key.pem                 ← 复制过去
├── resources/
│   └── views/
│       ├── eduroamlogin.blade.php         ← 复制过去
│       └── smtplogin.blade.php            ← 复制过去（可选）
└── routes/
    └── web.php                            ← 参考其中的路由，合并到你自己的 web.php
```

### 2. 添加路由

在你的 `routes/web.php` 中加入以下路由（参考项目中的 `web.php`）：

```php
use App\Http\Controllers\LoginController;
use App\Http\Controllers\LoginController_smtp;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::middleware('guest')->group(function () {
        // Eduroam + 官方认证 登录
        Route::get('/eduroam/login', [LoginController::class, 'showLoginForm']);
        Route::post('/eduroam/login', [LoginController::class, 'handleLogin']);
        
        // SMTP 邮箱登录（可选）
        Route::get('/smtp/login', [LoginController_smtp::class, 'showLoginForm']);
        Route::post('/smtp/login', [LoginController_smtp::class, 'handleLogin']);
    });
});
```

### 3. 环境要求

- PHP >= 7.4
- PHP 扩展：`curl`、`openssl`、`json`
- Laravel 框架（已包含 `Http` Facade 和 Eloquent ORM）

---

## 使用方式

### 用户端

1. 访问 `https://你的域名/auth/eduroam/login`
2. 输入**学号/工号**和**密码**
3. 选择认证方式：
   - **Eduroam** — 默认，走 eduroam 服务器验证
   - **官方认证** — 走学校 AnyShare 网盘 API 验证（密码 RSA 加密传输）
4. 点击登录

首次登录会自动注册账号，赠送 2000 积分。

### 认证方式对比

| | Eduroam | 官方认证 |
|------|---------|----------|
| 认证服务器 | eduroam.ecustvr.top | pan.ecust.edu.cn |
| 密码传输 | 明文（依赖 HTTPS） | RSA 公钥加密 |
| 适用场景 | 校园网/eduroam 用户 | 所有校内用户 |

---

## 常见问题

**Q: 官方认证失败提示"公钥文件不存在"？**

A: 检查 `storage/app/public_key.pem` 是否存在。如果公钥过期，可以用 `ecust-auth/extract_key.py` 重新提取：

```bash
cd ecust-auth
pip install cryptography
python extract_key.py
# 将生成的 public_key.pem 复制到 storage/app/
```

**Q: 想只用一种认证方式？**

A: 在 `eduroamlogin.blade.php` 中删除 radio 按钮组，并在 `LoginController.php` 的 `handleLogin()` 中把 `$authMethod` 写死为 `'eduroam'` 或 `'official'`。

**Q: SMTP 登录怎么用？**

A: SMTP 登录走的是学校企业邮箱（网易）验证，用户需输入完整邮箱地址和邮箱密码/授权码。访问 `/auth/smtp/login`。
