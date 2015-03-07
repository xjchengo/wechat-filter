<?php namespace Xjchen\WechatFilter;

use Curl\Curl;
use Carbon\Carbon;
use Config;
use Redirect;
use Request;
use Cache;
use Session;

class Helper {

    /**
     * 生成获取code的url
     *
     * @param $appid
     * @param $redirect_uri
     * @param string $scope
     * @return mixed
     */
    public static function get_code_url($appid, $redirect_uri, $scope='snsapi_userinfo')
    {
        $template_url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid=APPID&redirect_uri=REDIRECT_URI&response_type=code&scope=SCOPE#wechat_redirect';
        $url = str_replace(['APPID', 'REDIRECT_URI', 'SCOPE'], [$appid, $redirect_uri, $scope], $template_url);
        return $url;
    }

    /**
     * 生成获取access_token的url
     *
     * @param $appid
     * @param $secret
     * @return mixed
     */
    public static function get_global_access_token_url($appid, $secret)
    {
        $template_url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=APPID&secret=APPSECRET';
        $url = str_replace(['APPID', 'APPSECRET'], [$appid, $secret], $template_url);
        return $url;
    }

    /**
     * 生成获取微信Oauth Code的redirect
     *
     * @param string $scope
     * @return mixed
     * @throws WechatConfigRequiredException
     */
    public static function get_code_redirect($scope='snsapi_userinfo')
    {
        $appid = Config::get('wechat.appid', '');
        if (!$appid) {
            throw new WechatConfigRequiredException('wechat.base filter中缺少appid');
        }
        $getCodeUrl = self::get_code_url($appid, Request::fullUrl(), $scope);
        return Redirect::guest($getCodeUrl);
    }

    /**
     * 生成获取access_token的url
     *
     * @param $appid
     * @param $secret
     * @param $code
     * @return string
     */
    public static function get_access_token_url($appid, $secret, $code)
    {
        $template_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid=APPID&secret=SECRET&code=CODE&grant_type=authorization_code';
        $url = str_replace(['APPID', 'SECRET', 'CODE'], [$appid, $secret, $code], $template_url);
        return $url;
    }

    /**
     * 生成获取userinfo的url
     *
     * @param $access_token
     * @param $openid
     * @param string $lang
     * @return mixed
     */
    public static function get_global_userinfo_url($access_token, $openid, $lang='zh_CN')
    {
        $template_url = 'https://api.weixin.qq.com/cgi-bin/user/info?access_token=ACCESS_TOKEN&openid=OPENID&lang=zh_CN';
        $url = str_replace(['ACCESS_TOKEN', 'OPENID', 'LANG'], [$access_token, $openid, $lang], $template_url);
        return $url;
    }

    /**
     * 生成获取userinfo的url
     *
     * @param $access_token
     * @param $openid
     * @param string $lang
     * @return mixed
     */
    public static function get_userinfo_url($access_token, $openid, $lang='zh_CN')
    {
        $template_url = 'https://api.weixin.qq.com/sns/userinfo?access_token=ACCESS_TOKEN&openid=OPENID&lang=LANG';
        $url = str_replace(['ACCESS_TOKEN', 'OPENID', 'LANG'], [$access_token, $openid, $lang], $template_url);
        return $url;
    }

    /**
     * @param $url
     * @param bool $return_json
     * @return mixed|null
     * @throws CurlGetException
     * @throws \ErrorException
     */
    public static function curl_get_wrapper($url, $return_json=true)
    {
        $curl = new Curl();
        $curl->get($url);

        if ($curl->error) {
            // 抛出错误
            $error = "curl get error: code[{$curl->error_code}] error_message[{$curl->error_message}]";
            throw new CurlGetException($error);
        } else {
            if ($return_json) {
                return json_decode($curl->raw_response, true);
            } else {
                return $curl->raw_response;
            }
        }
    }

    /**
     * 获取global_access_token数组
     *
     * @return mixed|null
     * @throws CurlGetException
     * @throws GetAccessTokenException
     * @throws WechatConfigRequiredException
     */
    public static function get_global_access_token()
    {
        if (Cache::has('global_access_token')) {
            return Cache::get('global_access_token');
        }
        $appid = Config::get('wechat.appid', '');
        $secret = Config::get('wechat.secret', '');
        if (!$appid or !$secret) {
            throw new WechatConfigRequiredException('get_global_access_token中缺少appid或secret');
        }
        $getAccessTokenUrl = self::get_global_access_token_url($appid, $secret);
        $result = self::curl_get_wrapper($getAccessTokenUrl);
        if (isset($result['errcode'])) {
            throw new GetAccessTokenException('get_global_access_token get access token error: '.json_encode($result));
        }
        $expiresAt = Carbon::now()->addMinutes(($result['expires_in']/60)-1);
        Cache::put('global_access_token', $result['access_token'], $expiresAt);
        return $result['access_token'];
    }

    /**
     * 获取userinfo
     *
     * @param $openid
     * @param string $lang
     * @return mixed|null
     * @throws CurlGetException
     * @throws GetUserinfoException
     */

    public static function get_global_userinfo($openid, $lang='zh_CN')
    {
        $access_token = self::get_global_access_token();
        $getUserinfoUrl = self::get_global_userinfo_url($access_token, $openid, $lang);
        $result = self::curl_get_wrapper($getUserinfoUrl);
        if (isset($result['errcode'])) {
            throw new GetUserinfoException('get_global_userinfo error: '.json_encode($result));
        }
        Session::put('global_wechat_userinfo', $result);
        return $result;
    }

    /**
     * 获取access_token数组
     *
     * @param $code
     * @return mixed|null
     * @throws CurlGetException
     * @throws GetAccessTokenException
     * @throws WechatConfigRequiredException
     */
    public static function get_access_token($code)
    {
        $appid = Config::get('wechat.appid', '');
        $secret = Config::get('wechat.secret', '');
        if (!$appid or !$secret) {
            throw new WechatConfigRequiredException('wechat.base filter中缺少appid或secret');
        }
        $getAccessTokenUrl = self::get_access_token_url($appid, $secret, $code);
        $result = self::curl_get_wrapper($getAccessTokenUrl);
        if (isset($result['errcode'])) {
            throw new GetAccessTokenException('wechat.base filter get access token error: '.json_encode($result));
        }
        Session::put('oauth_time', time());
        Session::put('openid', $result['openid']);
        return $result;
    }

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
    public static function get_userinfo($access_token, $openid, $lang='zh_CN')
    {
        $getUserinfoUrl = self::get_userinfo_url($access_token, $openid, $lang);
        $result = self::curl_get_wrapper($getUserinfoUrl);
        if (isset($result['errcode'])) {
            throw new GetUserinfoException('wechat.base filter get userinfo error: '.json_encode($result));
        }
        Session::put('wechat_userinfo', $result);
        return $result;
    }
}