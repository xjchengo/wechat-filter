<?php namespace Xjchen\WechatFilter;

use Illuminate\Support\ServiceProvider;

class WechatFilterServiceProvider extends ServiceProvider {

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

    /**
     * Booting
     */
    public function boot()
    {
        $this->package('xjchen/wechat-filter', null, __DIR__);

        include __DIR__.'/filters.php';
    }

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

}
