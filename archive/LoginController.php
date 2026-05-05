<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Http;
use App\Models\User;
use Auth;
use Cache;
use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use App\Events;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use App\Services\EcustAuth;

class LoginController extends Controller
{ 
    public function showLoginForm()
    {
        // 显示登录表单的视图
        return view('eduroamlogin');  // 确保视图文件名与实际文件名匹配
    }
    
    public function handleLogin(Request $request)
    {
        $studentNumber = $request->input('student_number');
        $password = $request->input('password');
        $authMethod = $request->input('auth_method', 'eduroam'); // 默认 eduroam
    
        // 检查 $studentNumber 是否包含 @ 符号
        if (strpos($studentNumber, '@') !== false) {
            return back()->withErrors(['login' => '请仅输入学号/工号！']);
        }
        
        $emailForAuthentication = $studentNumber . '@ecust.edu.cn';
        
        // 检查 studentNumber 是否为5位
        if (strlen($studentNumber) != 5) {
            $emailForDatabase = $studentNumber . '@mail.ecust.edu.cn';
        } else {
            $emailForDatabase = $emailForAuthentication;
        }
        
        // 根据选择的认证方式进行认证
        if ($authMethod === 'official') {
            return $this->handleOfficialLogin($request, $studentNumber, $password, $emailForDatabase);
        } else {
            return $this->handleEduroamLogin($request, $studentNumber, $password, $emailForAuthentication, $emailForDatabase);
        }
    }
    
    /**
     * eduroam 认证方式
     */
    private function handleEduroamLogin(Request $request, $studentNumber, $password, $emailForAuthentication, $emailForDatabase)
    {
        try {
            // 进行远程身份验证
            $response = Http::asForm()->post('https://eduroam.ecustvr.top/cgi-bin/eduroam-test.cgi', [
                'login' => $emailForAuthentication,
                'password' => $password
            ]);
            
            // 检查是否成功连接到远程服务器
            if ($response->failed()) {
                return back()->withErrors(['login' => '远程服务器响应错误，请联系管理员。']);
            }
    
            // 根据远程服务的响应处理登录逻辑
            if (strpos($response->body(), 'EAP Failure') !== false) {
                return back()->withErrors(['login' => '用户名或密码错误，请重新输入。']);
            } elseif (strpos($response->body(), 'illegal') !== false) {
                return back()->withErrors(['login' => '登录失败，原因：非法操作，请联系管理员。']);
            } elseif (strpos($response->body(), 'success') !== false) {
                return $this->loginOrRegisterUser($request, $emailForDatabase, $studentNumber, $password);
            } else {
                return back()->withErrors(['login' => '登录失败，未知错误，请稍后重试或联系管理员。']);
            }
        } catch (Exception $e) {
            return back()->withErrors(['login' => '连接远程服务器时发生错误，请联系管理员。']);
        }
    }
    
    /**
     * 官方认证方式（AnyShare 网盘 API）
     */
    private function handleOfficialLogin(Request $request, $studentNumber, $password, $emailForDatabase)
    {
        try {
            $auth = new EcustAuth();
            $result = $auth->verifyUser($studentNumber, $password);
            
            if ($result['valid']) {
                return $this->loginOrRegisterUser($request, $emailForDatabase, $studentNumber, $password);
            } else {
                return back()->withErrors(['login' => $result['message']]);
            }
        } catch (Exception $e) {
            return back()->withErrors(['login' => '官方认证服务器连接失败，请稍后重试或联系管理员。']);
        }
    }
    
    /**
     * 登录或注册用户（两种认证方式共用）
     */
    private function loginOrRegisterUser(Request $request, $emailForDatabase, $studentNumber, $password)
    {
        $user = User::where('email', $emailForDatabase)->first();
        if (!$user) {
            // 用户不存在，创建新用户
            $user = new User();
            $user->email = $emailForDatabase;
            $user->nickname = $studentNumber;
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
    }
}