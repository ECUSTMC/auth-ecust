<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - 统一身份认证</title>
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
                <p class="login-box-msg">请使用您的统一身份认证账号登录</p>
                
                <!-- 显示简单的文字错误信息 -->
                @if ($errors->any())
                    <p style="color: red; font-size: 14px; text-align: center;">
                        @foreach ($errors->all() as $error)
                            {{ $error }}
                        @endforeach
                    </p>
                @endif

                <form action="/auth/eduroam/login" method="post">
                    @csrf  <!-- CSRF Token -->
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" placeholder="学号/工号" name="student_number" required>
                        <div class="input-group-append">
                            <div class="input-group-text">
                                <span class="fas fa-user-graduate"></span>
                            </div>
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
                    <div class="form-group mb-3">
                        <label style="font-size: 14px; color: #666;">认证方式</label>
                        <div class="d-flex">
                            <div class="custom-control custom-radio mr-3">
                                <input type="radio" id="authEduroam" name="auth_method" value="eduroam" class="custom-control-input" checked>
                                <label class="custom-control-label" for="authEduroam">Eduroam</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="authOfficial" name="auth_method" value="official" class="custom-control-input">
                                <label class="custom-control-label" for="authOfficial">官方认证</label>
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
	});
	</script>
</body>
</html>
