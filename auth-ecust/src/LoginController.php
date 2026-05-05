<?php

namespace AuthEcust;

use Illuminate\Http\Request;
use Http;
use App\Models\User;
use Auth;
use Cache;
use Illuminate\Contracts\Events\Dispatcher;
use App\Events;
use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Blessing\Filter;
use Vectorface\Whip\Whip;
use App\Rules\Captcha;
use Session;
use Illuminate\Support\Facades\Hash;

// 引入 PHPMailer
require_once base_path('vendor/phpmailer/phpmailer/src/PHPMailer.php');
require_once base_path('vendor/phpmailer/phpmailer/src/SMTP.php');
require_once base_path('vendor/phpmailer/phpmailer/src/Exception.php');
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class LoginController extends Controller
{
    private $authUrl = 'https://eduroam.ecustvr.top/cgi-bin/eduroam-test.cgi';
    private $smtpHost = 'smtphz.qiye.163.com';
    private $smtpPort = 465;
    private $smtpSecure = PHPMailer::ENCRYPTION_SMTPS;

    public function showLoginForm(Filter $filter)
    {
        $whip = new Whip();
        $ip = $whip->getValidIpAddress();
        $ip = $filter->apply('client_ip', $ip);
        $rows = [
            'AuthEcust::rows.login.notice',
            'AuthEcust::rows.login.form',
            'AuthEcust::rows.login.extra'
        ];

        return view('AuthEcust::login', [
            'rows' => $rows,
            'extra' => [
                'tooManyFails' => cache(sha1('login_fails_' . $ip)) > 3,
                'recaptcha' => option('recaptcha_sitekey'),
                'invisible' => (bool) option('recaptcha_invisible'),
            ],
        ]);
    }

    public function handleLogin(
        Request $request,
        Captcha $captcha,
        Dispatcher $dispatcher,
        Filter $filter
    ) {
        $data = $request->validate([
            'identification' => 'required',
            'password' => 'required',
        ]);
        $identification = $data['identification'];
        $password = $data['password'];
        $authMethod = $request->input('auth_method', 'official');

        $can = $filter->apply('can_login', null, [$identification, $password]);
        if ($can instanceof Rejection) {
            return json($can->getReason(), 1);
        }

        $authType = 'ecust_' . $authMethod;
        $dispatcher->dispatch('auth.login.attempt', [$identification, $password, $authType]);
        event(new Events\UserTryToLogin($identification, $authType));

        $whip = new Whip();
        $ip = $whip->getValidIpAddress();
        $ip = $filter->apply('client_ip', $ip);
        $loginFailsCacheKey = sha1('login_fails_' . $ip);
        $loginFails = (int) Cache::get($loginFailsCacheKey, 0);

        if ($loginFails > 3) {
            $request->validate(['captcha' => ['required', $captcha]]);
        }

        // 根据认证方式分发
        switch ($authMethod) {
            case 'official':
                return $this->handleOfficial($request, $identification, $password, $dispatcher, $ip, $loginFailsCacheKey, $loginFails);
            case 'smtp':
                return $this->handleSmtp($request, $identification, $password, $dispatcher, $ip, $loginFailsCacheKey, $loginFails);
            default:
                return $this->handleEduroam($request, $identification, $password, $dispatcher, $ip, $loginFailsCacheKey, $loginFails);
        }
    }

    /**
     * Eduroam 认证
     */
    private function handleEduroam(Request $request, $identification, $password, Dispatcher $dispatcher, $ip, $loginFailsCacheKey, $loginFails)
    {
        $emailForAuth = $identification . '@ecust.edu.cn';
        $emailForDB = $this->getEmailForDatabase($identification);

        $response = Http::asForm()->post($this->authUrl, [
            'login' => $emailForAuth,
            'password' => $password
        ]);

        if (strpos($response->body(), 'EAP Failure') !== false) {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $emailForDB, trans('AuthEcust::auth.ecust.error.credential'));
        } elseif (strpos($response->body(), 'illegal') !== false) {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $emailForDB, trans('AuthEcust::auth.ecust.error.illegal'));
        } elseif (strpos($response->body(), 'success') !== false) {
            return $this->loginSuccess($request, $emailForDB, $identification, $password, $dispatcher, $ip, $loginFailsCacheKey);
        } else {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $emailForDB, trans('AuthEcust::auth.ecust.error.unknown'));
        }
    }

    /**
     * 官方认证（AnyShare 网盘 API）
     */
    private function handleOfficial(Request $request, $identification, $password, Dispatcher $dispatcher, $ip, $loginFailsCacheKey, $loginFails)
    {
        $emailForDB = $this->getEmailForDatabase($identification);

        try {
            $auth = new EcustAuth();
            $result = $auth->verifyUser($identification, $password);

            if ($result['valid']) {
                return $this->loginSuccess($request, $emailForDB, $identification, $password, $dispatcher, $ip, $loginFailsCacheKey);
            } else {
                return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $emailForDB, $result['message']);
            }
        } catch (\Exception $e) {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $emailForDB, trans('AuthEcust::auth.ecust.error.server'));
        }
    }

    /**
     * SMTP 邮箱认证
     */
    private function handleSmtp(Request $request, $identification, $password, Dispatcher $dispatcher, $ip, $loginFailsCacheKey, $loginFails)
    {
        // 检查邮箱格式
        if (strpos($identification, '@') === false) {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $identification, trans('AuthEcust::auth.ecust.error.email_format'));
        }

        // 检查邮箱域名
        if (!preg_match('/@(ecust\.edu\.cn|mail\.ecust\.edu\.cn|alumni\.ecust\.edu\.cn)$/', $identification)) {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $identification, trans('AuthEcust::auth.ecust.error.email_domain'));
        }

        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $this->smtpHost;
            $mail->Port = $this->smtpPort;
            $mail->SMTPSecure = $this->smtpSecure;
            $mail->SMTPAuth = true;
            $mail->Username = $identification;
            $mail->Password = $password;

            if (!$mail->smtpConnect()) {
                return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $identification, trans('AuthEcust::auth.ecust.error.smtp_failed'));
            }

            $mail->smtpClose();
            return $this->loginSuccess($request, $identification, explode('@', $identification)[0], $password, $dispatcher, $ip, $loginFailsCacheKey);
        } catch (PHPMailerException $e) {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $identification, trans('AuthEcust::auth.ecust.error.smtp_failed'));
        } catch (\Exception $e) {
            return $this->loginFailed($loginFailsCacheKey, $loginFails, $dispatcher, $identification, trans('AuthEcust::auth.ecust.error.server'));
        }
    }

    /**
     * 根据学号位数判断数据库存储邮箱
     * 5位 → 教职工 → @ecust.edu.cn
     * 其他 → 学生 → @mail.ecust.edu.cn
     */
    private function getEmailForDatabase($identification)
    {
        if (strlen($identification) == 5) {
            return $identification . '@ecust.edu.cn';
        }
        return $identification . '@mail.ecust.edu.cn';
    }

    /**
     * 登录成功处理
     */
    private function loginSuccess(Request $request, $emailForDB, $identification, $password, Dispatcher $dispatcher, $ip, $loginFailsCacheKey)
    {
        Session::forget('login_fails');
        Cache::forget($loginFailsCacheKey);

        $user = User::where('email', $emailForDB)->first();
        if (!$user) {
            $user = new User();
            $user->email = $emailForDB;
            $user->nickname = $identification;
            $user->score = option('user_initial_score');
            $user->avatar = 0;
            $user->password = Hash::make($password);
            $user->ip = $ip;
            $user->permission = User::NORMAL;
            $user->register_at = Carbon::now();
            $user->last_sign_at = Carbon::now();
            $user->verified = true;
            $user->save();
            Auth::login($user, true);
            $dispatcher->dispatch('auth.registration.completed', [$user]);
        }

        $dispatcher->dispatch('auth.login.ready', [$user]);
        Auth::login($user, $request->input('keep'));
        $dispatcher->dispatch('auth.login.succeeded', [$user]);
        event(new Events\UserLoggedIn($user));

        return json(trans('auth.login.success'), 0, [
            'redirectTo' => $request->session()->pull('last_requested_path', url('/user')),
        ]);
    }

    /**
     * 登录失败处理
     */
    private function loginFailed($loginFailsCacheKey, $loginFails, Dispatcher $dispatcher, $emailForDB, $errorMsg)
    {
        $loginFails++;
        Cache::put($loginFailsCacheKey, $loginFails, 3600);

        $user = User::where('email', $emailForDB)->first();
        if (isset($user)) {
            $dispatcher->dispatch('auth.login.failed', [$user, $loginFails]);
        }

        return json($errorMsg, 1, [
            'login_fails' => $loginFails,
        ]);
    }
}
