<?php
if (is_dir(__DIR__.'/../vendor')) {
    require __DIR__.'/../vendor/php-curl-class/php-curl-class/src/Curl/Curl.php';
} else {
    require __DIR__.'/../../../php-curl-class/php-curl-class/src/Curl/Curl.php';
}


use Curl\Curl;
use Xjchen\WechatFilter\CurlGetException;
use Xjchen\WechatFilter\WechatConfigRequiredException;
use Xjchen\WechatFilter\GetAccessTokenException;
use Xjchen\WechatFilter\GetUserinfoException;

if (!function_exists('get_code_url')) {

    /**
     * 生成获取code的url
     *
     * @param $appid
     * @param $redirect_uri
     * @param string $scope
     * @return mixed
     */
    function get_code_url($appid, $redirect_uri, $scope='snsapi_userinfo')
    {
        $template_url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=APPID&redirect_uri=REDIRECT_URI&response_type=code&scope=SCOPE#wechat_redirect';
        $url = str_replace(['APPID', 'REDIRECT_URI', 'SCOPE'], [$appid, $redirect_uri, $scope], $template_url);
        return $url;
    }
}

if (!function_exists('get_code_redirect')) {

    /**
     * 生成获取微信Oauth Code的redirect
     *
     * @param string $scope
     * @return mixed
     * @throws WechatConfigRequiredException
     */
    function get_code_redirect($scope='snsapi_userinfo')
    {
        $appid = Config::get('wechat.appid', '');
        if (!$appid) {
            throw new WechatConfigRequiredException('wechat.base filter中缺少appid');
        }
        $getCodeUrl = get_code_url($appid, Request::fullUrl(), $scope);
        return Redirect::guest($getCodeUrl);
    }
}

if (!function_exists('get_access_token_url')) {
    /**
     * 生成获取access_token的url
     *
     * @param $appid
     * @param $secret
     * @param $code
     * @return string
     */
    function get_access_token_url($appid, $secret, $code)
    {
        $template_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code';
        $url = str_replace(['APPID', 'SECRET', 'CODE'], [$appid, $secret, $code], $template_url);
        return $url;
    }
}

if (!function_exists('get_userinfo_url')) {
    /**
     * 生成获取userinfo的url
     *
     * @param $access_token
     * @param $openid
     * @param string $lang
     * @return mixed
     */
    function get_userinfo_url($access_token, $openid, $lang='zh_CN')
    {
        $template_url = 'https://api.weixin.qq.com/sns/userinfo?access_token=ACCESS_TOKEN&openid=OPENID&lang=LANG';
        $url = str_replace(['ACCESS_TOKEN', 'OPENID', 'LANG'], [$access_token, $openid, $lang], $template_url);
        return $url;
    }
}

if (!function_exists('basic_curl_get_wrapper')) {
    /**
     * @param $url
     * @param bool $return_json
     * @return mixed|null
     * @throws CurlGetException
     * @throws ErrorException
     */
    function curl_get_wrapper($url, $return_json=true)
    {
        $curl = new Curl();
        $curl->get($url);

        if ($curl->error) {
            // 抛出错误
            $error = "curl get error: code[{$curl->error_code}] error_message[{$curl->error_message}]";
            throw new CurlGetException($error);
        } else {
            if ($return_json) {
                return json_decode($curl->response, true);
            } else {
                return $curl->response;
            }
        }
    }
}

if (!function_exists('get_access_token')) {
    /**
     * 获取access_token数组
     *
     * @param $code
     * @return mixed|null
     * @throws CurlGetException
     * @throws GetAccessTokenException
     * @throws WechatConfigRequiredException
     */
    function get_access_token($code)
    {
        $appid = Config::get('wechat.appid', '');
        $secret = Config::get('wechat.secret', '');
        if (!$appid or !$secret) {
            throw new WechatConfigRequiredException('wechat.base filter中缺少appid或secret');
        }
        $getAccessTokenUrl = get_access_token_url($appid, $secret, $code);
        $result = curl_get_wrapper($getAccessTokenUrl);
        if (isset($result['errcode'])) {
            throw new GetAccessTokenException('wechat.base filter get access token error: '.json_encode($result));
        }
        Session::put('oauth_time', time());
        Session::put('openid', $result['openid']);
        return $result;
    }
}

if (!function_exists('get_userinfo')) {

    /**
     * 获取userinfo
     *
     * @param $access_token
     * @param $openid
     * @param string $lang
     * @return mixed|null
     * @throws CurlGetException
     * @throws GetUserinfoException
     */

    function get_userinfo($access_token, $openid, $lang='zh_CN')
    {
        $getUserinfoUrl = get_userinfo_url($access_token, $openid, $lang);
        $result = curl_get_wrapper($getUserinfoUrl);
        if (isset($result['errcode'])) {
            throw new GetUserinfoException('wechat.base filter get userinfo error: '.json_encode($result));
        }
        Session::put('wechat_userinfo', $result);
        return $result;
    }
}
