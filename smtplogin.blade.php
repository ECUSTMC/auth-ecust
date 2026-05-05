<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 邮箱认证</title>
    <!-- Bootstrap CSS -->
    <link href="https://mirrors.sustech.edu.cn/cdnjs/ajax/libs/twitter-bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="/app/style.7eb5d06.css" rel="stylesheet" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://mirrors.sustech.edu.cn/cdnjs/ajax/libs/font-awesome/5.15.4/css/all.min.css" crossorigin>
</head>
<body class="hold-transition login-page">
    <div class="login-box">
        <div class="login-logo">
            <a href="/">ECUSTMC</a>
        </div>
        <div class="card">
            <div class="card-body login-card-body">
                <p class="login-box-msg">请使用您的邮箱账号登录</p>
                <!-- 显示简单的文字错误信息 -->
                @if ($errors->any())
                    <p style="color: red; font-size: 14px; text-align: center;">
                        @foreach ($errors->all() as $error)
                            {{ $error }}
                        @endforeach
                    </p>
                @endif
                <form action="/auth/smtp/login" method="post">
                    @csrf  <!-- CSRF Token -->
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="邮箱" name="student_number" id="emailInput" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user-graduate"></span>
                            </div>
                        </div>
                        <div class="dropdown-menu" id="emailSuffixDropdown">
                            <a class="dropdown-item" href="#" onclick="completeEmail('@mail.ecust.edu.cn')">@mail.ecust.edu.cn</a>
                            <a class="dropdown-item" href="#" onclick="completeEmail('@ecust.edu.cn')">@ecust.edu.cn</a>
                            <a class="dropdown-item" href="#" href="#" onclick="completeEmail('@alumni.ecust.edu.cn')">@alumni.ecust.edu.cn</a>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" class="form-control" placeholder="密码" name="password" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-lock"></span>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" id="loginButton" class="btn btn-primary btn-block">登录</button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-3"><a href="/auth/login">通过用户名/邮箱登录</a></div>
                </form>
            </div>
        </div>
    </div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var loginForm = document.querySelector('form');
    loginForm.addEventListener('submit', function() {
        var loginButton = document.getElementById('loginButton');
        loginButton.disabled = true; // 禁用按钮
        loginButton.innerText = '等待中...'; // 更改按钮文字
    });
    
    // 邮箱后缀自动补全功能
    var emailInput = document.getElementById('emailInput');
    var emailSuffixDropdown = document.getElementById('emailSuffixDropdown');
    
    emailInput.addEventListener('input', function() {
        var value = emailInput.value;
        if (value.includes('@')) {
            emailSuffixDropdown.style.display = 'none';
            return;
        }
        
        if (value.length > 0) {
            emailSuffixDropdown.style.display = 'block';
        } else {
            emailSuffixDropdown.style.display = 'none';
        }
    });
    
    // 点击其他地方隐藏下拉菜单
    document.addEventListener('click', function(e) {
        if (e.target !== emailInput) {
            emailSuffixDropdown.style.display = 'none';
        }
    });
});

function completeEmail(suffix) {
    var emailInput = document.getElementById('emailInput');
    emailInput.value = emailInput.value + suffix;
    document.getElementById('emailSuffixDropdown').style.display = 'none';
    return false;
}
</script>
</body>
</html>