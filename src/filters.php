<?php
/**
 * 微信filter，用于OAuth接口认证
 * 需要在Config在设置好wechat的相关信息
 */



/**
 * 获取用户的openid，不需要用户授权
 *
 * 判断session中是否有该用户的openid，如果没有，Oauth获取
 */
Route::filter('wechat.base', function() {
    $userAgent = Request::header('User-Agent');
    $environment = App::environment();
    if ($environment == 'local' and !preg_match('#MicroMessenger#i', $userAgent)) {
        Session::put('oauth_time', time());
        Session::put('openid', Input::get('openid', ''));
    } else {
        $lastOauthTime = Session::get('oauth_time', 0);
        $timeNow = time();
        if (!Session::has('openid') or (Input::has('wechat-force') and ($lastOauthTime < ($timeNow - 5)))) {
            if (Input::has('code')) {
                //获取openid
                try {
                    get_access_token(Input::get('code'));
                } catch (Exception $e) {
                    Log::error($e->getMessage());
                    return '获取token错误';
                }
                return Redirect::intended(Request::fullUrl());
            } else {
                try {
                    $getCodeRedirect = get_code_redirect('snsapi_base');
                    return $getCodeRedirect;
                } catch (Exception $e) {
                    Log::error($e->getMessage());
                }
                return '未配置微信号';
            }
        }
    }
});

/**
 * 获取用户的身份信息，需要用户授权
 */
Route::filter('wechat.userinfo', function() {
    $userAgent = Request::header('User-Agent');
    if (!preg_match('#MicroMessenger#i', $userAgent)) {
        return App::abort(500, '只能在微信浏览器中打开');
    }
    $lastOauthTime = Session::get('oauth_time', 0);
    $timeNow = time();
    if (!Session::has('wechat_userinfo') or (Input::has('wechat-force') and ($lastOauthTime < ($timeNow - 30)))) {
        if (Input::has('code')) {
            //获取openid
            try {
                $token = get_access_token(Input::get('code'));
            } catch (Exception $e) {
                Log::error($e->getMessage());
                return '获取token错误';
            }

            try {
                get_userinfo($token['access_token'], $token['openid']);
            } catch (Exception $e) {
                Log::error($e->getMessage());
                return '获取userinfo错误';
            }
            return Redirect::intended(Request::fullUrl());
        } else {
            try {
                $getCodeRedirect = get_code_redirect('snsapi_userinfo');
                return $getCodeRedirect;
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
            return '未配置微信号';
        }
    }
});
