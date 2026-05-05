<?php

use App\Services\Hook;
use Blessing\Filter;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;

return function (Dispatcher $events, Filter $filter) {
    // 注册路由
    Hook::addRoute(function () {
        Route::namespace('AuthEcust')
            ->prefix('auth')
            ->name('auth.')
            ->middleware(['web', 'guest'])
            ->group(function () {
                Route::get('/ecust/login', 'LoginController@showLoginForm')
                    ->name('auth.ecust.login');
                Route::post('/ecust/login', 'LoginController@handleLogin')
                    ->name('auth.ecust.login.post');
            });
    });

    // 在登录/注册页面注入第三方登录入口
    View::composer('AuthEcust::providers', function ($view) use ($filter) {
        $providers = $filter->apply('ecust_providers', collect());
        $view->with('providers', $providers);
    });

    $filter->add('auth_page_rows:login', function ($rows) {
        $length = count($rows);
        array_splice($rows, $length - 1, 0, ['AuthEcust::providers']);
        return $rows;
    });

    $filter->add('auth_page_rows:register', function ($rows) {
        $rows[] = 'AuthEcust::providers';
        return $rows;
    });

    // 注册 ECUST 认证入口
    $filter->add('ecust_providers', function (Collection $providers) {
        $providers->put('ecust/login', [
            'icon' => 'university fas',
            'displayName' => trans('AuthEcust::auth.ecust.login'),
        ]);
        return $providers;
    });
};
