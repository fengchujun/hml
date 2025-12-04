<script>
	import auth from 'common/js/auth.js';
	import colorList from 'common/js/style_color.js'
	import {
		Weixin
	} from 'common/js/wx-jssdk.js';

	export default {
		mixins: [auth],
		onLaunch: function(options) {
			uni.hideTabBar();

			// #ifdef MP
			const updateManager = uni.getUpdateManager();
			updateManager.onCheckForUpdate(function(res) {
				// 请求完新版本信息的回调
			});

			updateManager.onUpdateReady(function(res) {
				uni.showModal({
					title: '更新提示',
					content: '新版本已经准备好，是否重启应用？',
					success(res) {
						if (res.confirm) {
							// 新的版本已经下载好，调用 applyUpdate 应用新版本并重启
							updateManager.applyUpdate();
						}
					}
				});
			});

			updateManager.onUpdateFailed(function(res) {
				// 新的版本下载失败
			});
			// #endif

			// #ifdef H5
			if (uni.getSystemInfoSync().platform == 'ios') {
				uni.setStorageSync('initUrl', location.href);
			}
			// #endif

			uni.onNetworkStatusChange(function(res) {
				if (!res.isConnected) {
					uni.showModal({
						title: '网络失去链接',
						content: '请检查网络链接',
						showCancel: false
					});
				}
			});


			// 存储到store中

			// 主题风格
			if (uni.getStorageSync('themeStyle')) {
				this.$store.commit('setThemeStyle', colorList[uni.getStorageSync('themeStyle')]);
			}

			// 插件是否存在
			if (uni.getStorageSync('addonIsExist')) {
				this.$store.commit('setAddonIsExist', uni.getStorageSync('addonIsExist'));
			}

			// 默认图
			if (uni.getStorageSync('defaultImg')) {
				this.$store.commit('setDefaultImg', uni.getStorageSync('defaultImg'));
			}

			// 站点信息
			if (uni.getStorageSync('siteInfo')) {
				this.$store.commit('setSiteInfo', uni.getStorageSync('siteInfo'));
			}

			// 门店配置
			if (uni.getStorageSync('globalStoreConfig')) {
				this.$store.commit('setGlobalStoreConfig', uni.getStorageSync('globalStoreConfig'));
			}

			// 门店信息
			if (uni.getStorageSync('globalStoreInfo')) {
				this.$store.commit('setGlobalStoreInfo', uni.getStorageSync('globalStoreInfo'));
			}

			// 默认门店信息
			if (uni.getStorageSync('defaultStoreInfo')) {
				this.$store.commit('setDefaultStoreInfo', uni.getStorageSync('defaultStoreInfo'));
			}

			// 客服配置
			if (uni.getStorageSync('servicerConfig')) {
				this.$store.commit('setServicerConfig', uni.getStorageSync('servicerConfig'));
			}

			// 版权信息
			if (uni.getStorageSync('copyright')) {
				this.$store.commit('setCopyright', uni.getStorageSync('copyright'));
			}

			// 地址配置
			if (uni.getStorageSync('mapConfig')) {
				this.$store.commit('setMapConfig', uni.getStorageSync('mapConfig'));
			}

			if (uni.getStorageSync('token')) {
				this.$store.commit('setToken', uni.getStorageSync('token'));
			}

			this.$store.dispatch('init');
			// 会员信息
			if (uni.getStorageSync('memberInfo')) {
				this.$store.commit('setMemberInfo', uni.getStorageSync('memberInfo'));

				// 查询购物车信息
				this.$store.dispatch('getCartNumber');
			}

			// #ifdef H5
			// 自动授权登录
			// 未登录情况下
			if (!uni.getStorageSync('memberInfo')) {
				this.getAuthInfo();
			}
			if (this.$store.state.token) {
				this.$api.sendRequest({
					url: '/api/member/info',
					success: (res) => {
						if (res.code >= 0) {
							this.$store.commit('setMemberInfo', res.data);
						}
					}
				});
			}
			// #endif

			// #ifdef MP-ALIPAY
			if (options.query && options.query.m) uni.setStorageSync('source_member', options.query.m);
			// #endif

		// 处理分享进来的推荐人参数（小程序启动时）
		// #ifdef MP-WEIXIN
		if (options.query && options.query.source_member) {
			uni.setStorageSync('source_member', options.query.source_member);
			// 标记需要登录检查（在第一个页面加载时会检查）
			if (!uni.getStorageSync('token')) {
				uni.setStorageSync('needLoginCheck', '1');
			}
		}
		// #endif
		},
	onShow: function(options) {
		// #ifdef MP
		// 处理分享进来的推荐人参数
		if (options && options.query) {
			// 微信小程序使用 source_member
			if (options.query.source_member) {
				uni.setStorageSync('source_member', options.query.source_member);
				// 如果未登录，需要先登录
				if (!this.$store.state.token) {
					// 保存当前要访问的页面路径（用于登录后跳转回来）
					let currentPath = this.$util.getCurrentRoute().path;
					if (currentPath && currentPath != '/pages_tool/login/index' && currentPath != '/pages_tool/login/login') {
						uni.setStorageSync('initiateLogin', currentPath);
						// 延迟跳转，确保页面已经加载
						setTimeout(() => {
							this.$util.redirectTo('/pages_tool/login/index');
						}, 500);
						return;
					}
				}
			}
			// 支付宝小程序使用 m
			if (options.query.m) {
				uni.setStorageSync('source_member', options.query.m);
				// 如果未登录，需要先登录
				if (!this.$store.state.token) {
					let currentPath = this.$util.getCurrentRoute().path;
					if (currentPath && currentPath != '/pages_tool/login/index' && currentPath != '/pages_tool/login/login') {
						uni.setStorageSync('initiateLogin', currentPath);
						setTimeout(() => {
							this.$util.redirectTo('/pages_tool/login/index');
						}, 500);
						return;
					}
				}
			}
		}

		// 检查启动时的登录标志（处理onLaunch时设置的标志）
		if (uni.getStorageSync('needLoginCheck') == '1') {
			uni.removeStorageSync('needLoginCheck');
			if (!this.$store.state.token) {
				let currentPath = this.$util.getCurrentRoute().path;
				if (currentPath && currentPath != '/pages_tool/login/index' && currentPath != '/pages_tool/login/login') {
					uni.setStorageSync('initiateLogin', currentPath);
					setTimeout(() => {
						this.$util.redirectTo('/pages_tool/login/index');
					}, 500);
					return;
				}
			}
		}

		// 自动授权登录
		if (this.$store.state.token) {
			this.$api.sendRequest({
				url: '/api/member/info',
				success: (res) => {
					if (res.code >= 0) {
						this.$store.commit('setMemberInfo', res.data);
					}
				}
			});
		}else{
			this.getAuthInfo();
		}
		// #endif
	},
		onHide: function() {},
		methods: {
			/**
			 * 获取授权信息
			 */
			getAuthInfo() {
				// #ifdef H5
				if (this.$util.isWeiXin()) {
					this.$util.getUrlCode(urlParams => {
						if (urlParams.source_member) uni.setStorageSync('source_member', urlParams.source_member);

						if (urlParams.code == undefined) {
							this.$api.sendRequest({
								url: '/wechat/api/wechat/authcode',
								data: {
									redirect_url: location.href,
									scopes: 'snsapi_userinfo'
								},
								success: res => {
									if (res.code >= 0) {
										location.href = res.data;
									}
								}
							});
						} else {
							this.$api.sendRequest({
								url: '/wechat/api/wechat/authcodetoopenid',
								data: {
									code: urlParams.code
								},
								success: res => {
									if (res.code >= 0) {
										let data = {};
										if (res.data.openid) data.wx_openid = res.data.openid;
										if (res.data.unionid) data.wx_unionid = res.data.unionid;
										if (res.data.userinfo) Object.assign(data, res.data.userinfo);
										this.authLogin(data);
									}
								}
							});
						}
					});
				}
				// #endif

				// #ifdef MP
				this.getCode(data => {
					this.authLogin(data, 'authOnlyLogin');
				});
				// #endif
			},
			/**
			 * 授权登录
			 */
			authLogin(data, type = 'authLogin') {
				if (uni.getStorageSync('source_member')) data.source_member = uni.getStorageSync('source_member');

				uni.setStorageSync('authInfo', data);

				this.$api.sendRequest({
					url: type == 'authLogin' ? '/api/login/auth' : '/api/login/authonlylogin',
					data,
					success: res => {
						if (res.code >= 0) {
							this.$store.commit('setToken', res.data.token);
							this.getMemberInfo()
							this.$store.dispatch('getCartNumber');
							this.$store.dispatch('init');
						}
					}
				});
			},
			/**
			 * 公众号分享设置
			 */
			shareConfig() {
				this.$api.sendRequest({
					url: '/wechat/api/wechat/share',
					data: {
						url: window.location.href
					},
					success: res => {
						if (res.code == 0) {
							var wxJS = new Weixin();
							wxJS.init(res.data.jssdk_config);

							let share_data = JSON.parse(JSON.stringify(res.data.share_config.data));
							if (share_data) {
								wxJS.setShareData({
										title: share_data.title,
										desc: share_data.desc,
										link: share_data.link,
										imgUrl: this.$util.img(share_data.imgUrl)
									},
									res => {
										console.log(res);
									}
								);
							}

							let hideOptionMenu = res.data.share_config.permission.hideOptionMenu;
							let hideMenuItems = res.data.share_config.permission.hideMenuItems;

							if (hideOptionMenu) {
								wxJS.weixin.hideOptionMenu(); //屏蔽分享好友等按钮
							} else {
								wxJS.weixin.showOptionMenu(); //放开分享好友等按钮
							}
						}
					},
					fail: err => {}
				});
			},
			getMemberInfo() {
				this.$api.sendRequest({
					url: '/api/member/info',
					success: (res) => {
						if (res.code >= 0) {
							// 登录成功，存储会员信息
							this.$store.commit('setMemberInfo', res.data);
							this.$store.dispatch('init');
						}
					}
				});
			}
		},
		watch: {
			$route: {
				handler(newName, oldName) {
					if (this.$util.isWeiXin()) {
						this.shareConfig();
					}
				},
				// 代表在watch里声明了firstName这个方法之后立即先去执行handler方法
				immediate: true
			}
		}
	};
</script>
<style lang="scss">
	@import './common/css/main.scss';
	@import './common/css/iconfont.css';
	@import './common/css/icondiy.css'; // 自定义图标库
	@import './common/css/icon/extend.css'; // 扩展图标库
</style>