<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Auth;
use Exception;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

// 引入 PHPMailer
require_once base_path('vendor/phpmailer/phpmailer/src/PHPMailer.php');
require_once base_path('vendor/phpmailer/phpmailer/src/SMTP.php');
require_once base_path('vendor/phpmailer/phpmailer/src/Exception.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class LoginController_smtp extends Controller
{ 
    public function showLoginForm()
    {
        // 显示登录表单的视图
        return view('smtplogin');  // 确保视图文件名与实际文件名匹配
    }
    
    public function handleLogin(Request $request)
    {
        $email = $request->input('student_number');
        $password = $request->input('password');
    
        // 检查 $email 是否包含 @ 符号
        if (strpos($email, '@') === false) {
            return back()->withErrors(['login' => '请输入正确邮箱格式！']);
        }
        
        // 使用正则表达式检查邮箱域名
        if (!preg_match('/@(ecust\.edu\.cn|mail\.ecust\.edu\.cn|alumni\.ecust\.edu\.cn)$/', $email)) {
            return back()->withErrors(['login' => '仅限本校邮箱登录！']);
        }
        
        $nickname = explode('@', $email)[0];
        
        try {
            // 使用SMTP进行身份验证
            $mail = new PHPMailer(true);

            // 配置SMTP
            $mail->isSMTP();
            $mail->Host = 'smtphz.qiye.163.com';
            $mail->Port = 465;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->SMTPAuth = true;
            $mail->Username = $email;
            $mail->Password = $password;

            // 尝试连接到SMTP服务器
            if (!$mail->smtpConnect()) {
                return back()->withErrors(['login' => 'SMTP连接失败，请检查用户名和密码。如有设置邮箱授权码，请使用授权码！']);
            }

            // 关闭连接
            $mail->smtpClose();

            // 验证成功，检查邮箱账户是否存在
            $user = User::where('email', $email)->first();
            if (!$user) {
                // 用户不存在，创建新用户
                $user = new User();
                $user->email = $email;
                $user->nickname = $nickname;
                $user->score = 2000;
                $user->avatar = 0;
                $user->verified = 1;
                $user->password = Hash::make($password);
                $user->ip = $request->ip();
                $user->permission = User::NORMAL;
                $user->register_at = Carbon::now();
                $user->last_sign_at = Carbon::now()->subDay();
                $user->save();
                Auth::login($user, true);
                return redirect('/user')->with('status', '注册成功并已登录。');
            }
            Auth::login($user, true);
            return redirect('/user')->with('status', '登录成功。');

        } catch (PHPMailerException $e) {
            // 捕获SMTP异常并返回错误信息
            return back()->withErrors(['login' => 'SMTP身份验证失败，请检查用户名和密码。如有设置邮箱授权码，请使用授权码！']);
        } catch (Exception $e) {
            // 捕获其他异常并返回错误信息
            return back()->withErrors(['login' => '连接SMTP服务器时发生错误，请联系管理员。']);
        }
    }
}