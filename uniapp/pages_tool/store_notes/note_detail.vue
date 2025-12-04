<template>
	<page-meta :page-style="themeColor"></page-meta>
	<!-- 设置导航标题为会客厅标题 -->
	<view class="goods-detail">
		<view class="goods-item" v-if="noteType == 'goods_item'">
			<!-- 只显示会客厅内容 -->
			<view class="item-content">
				<ns-mp-html :content="goodsItemInfo.note_content"></ns-mp-html>
			</view>

			<!-- 底部浮层：微信、电话、预约到店体验 -->
			<view class="lounge-actions">
				<view class="action-btn" @click="showWechatQrcode" v-if="goodsItemInfo.wechat_qrcode">
					<text class="iconfont iconweixin"></text>
					<text>微信</text>
				</view>
				<view class="action-btn" @click="callPhone" v-if="goodsItemInfo.phone">
					<text class="iconfont icondianhua"></text>
					<text>电话</text>
				</view>
				<view class="action-btn action-primary" @click="makeReservation" v-if="goodsItemInfo.support_reservation == 1">
					<text>预约到店体验</text>
				</view>
			</view>
		</view>
		<!-- 掌柜说 -->
		<view class="shop-said" v-else-if="noteType == 'shop_said'">
			<text class="said-title">{{ shopSaidInfo.note_title }}</text>
			<text class="said-time" v-if="shopSaidInfo.is_show_release_time == 1">{{ $util.timeStampTurnTime(shopSaidInfo.create_time, 'Y-m-d') }}</text>
			<view class="said-content">
				<!-- <rich-text :nodes="shopSaidInfo.note_content"></rich-text> -->
				<ns-mp-html :content="shopSaidInfo.note_content"></ns-mp-html>
			</view>
			<view class="said-goods">
				<view class="commodity-item" v-if="shopSaidInfo.goods_list" v-for="(goodsItme, goodsIndex) in shopSaidInfo.goods_list" :key="goodsIndex" @click="redirectToGoods(goodsItme.goods_id, 'shop_said')">
					<image class="commodity-img" :src="$util.img(goodsItme.goods_image.split(',')[0])" mode="aspectFit"/>
					<view class="commodity-content">
						<text class="commodity-name">{{ goodsItme.goods_name }}</text>
						<text class="commodity-price color-base-text">{{ goodsItme.price }}</text>
					</view>
				</view>
			</view>
			<view class="rest-info">
				<text v-if="shopSaidInfo.is_show_read_num == 1">
					阅读
					<text>{{ shopSaidInfo.initial_read_num + shopSaidInfo.read_num }}</text>
				</text>
				<text v-if="shopSaidInfo.is_show_dianzan_num == 1" @click="giveLike">
					<text class="iconfont icon-likefill color-base-text" v-if="giveLikeIdent"></text>
					<text class="iconfont icon-gz" v-if="!giveLikeIdent"></text>
					<text>{{ shopSaidInfo.initial_dianzan_num + shopSaidInfo.dianzan_num }}</text>
				</text>
			</view>
			<view class="said-action">
				<text @click="giveLike" v-if="!giveLikeIdent" class="iconfont icon-dianzan"></text>
				<text @click="giveLike" v-if="giveLikeIdent" class="iconfont icon-dianzan1 color-base-text active"></text>
				<!-- #ifdef MP -->
				<button type="primary" open-type="share" class="iconfont icon-share"></button>
				<!-- #endif -->
			</view>
		</view>
		<loading-cover ref="loadingCover"></loading-cover>
		<!-- 悬浮按钮 -->
		<hover-nav></hover-nav>
		<ns-login ref="login"></ns-login>

		<!-- #ifdef MP-WEIXIN -->
		<!-- 小程序隐私协议 -->
		<privacy-popup ref="privacyPopup"></privacy-popup>
		<!-- #endif -->
	</view>
</template>

<script>
	import htmlParser from '@/common/js/html-parser';
	export default {
		components: {},
		data() {
			return {
				noteId: '',
				noteType: '',
				goodsItemInfo: {},
				shopSaidInfo: {},
				giveLikeIdent: false,
				giveLikeFlag: false,
				//分享时详情所用图片
				shareImg: '',
				// 二维码弹窗标志
				showQrcodeModal: false
			};
		},
		onLoad(options) {
			setTimeout( () => {
				if (!this.addonIsExist.notes) {
					this.$util.showToast({
						title: '商家未开启店铺笔记',
						mask: true,
						duration: 2000
					});
					setTimeout(() => {
						this.$util.redirectTo('/pages/index/index');
					}, 2000);
				}
			},1000);

			//小程序分享接收source_member
			if (options.source_member) {
				uni.setStorageSync('source_member', options.source_member);
			}
			// 小程序扫码进入，接收source_member
			if (options.scene) {
				var sceneParams = decodeURIComponent(options.scene);
				sceneParams = sceneParams.split('&');
				if (sceneParams.length) {
					sceneParams.forEach(item => {
						if (item.indexOf('sku_id') != -1) this.skuId = item.split('-')[1];
						if (item.indexOf('m') != -1) uni.setStorageSync('source_member', item.split('-')[1]);
						if (item.indexOf('is_test') != -1) uni.setStorageSync('is_test', 1);
					});
				}
			}

			if (options.note_id) {
				this.noteId = options.note_id;
				this.getNoteDetail();
			} else {
				this.$util.redirectTo('/pages_tool/store_notes/note_list', {}, 'redirectTo');
			}
		},
		onShow() {
			
					if (this.noteType == 'goods_item' && this.goodsItemInfo.note_title) {
						uni.setNavigationBarTitle({
							title: this.goodsItemInfo.note_title || '会客厅详情'
						});
					}
			if (this.storeToken) {
				//记录分享关系
				if (uni.getStorageSync('source_member')) {
					this.$util.onSourceMember(uni.getStorageSync('source_member'));
				}

				this.isDianzan();
			}
		},

		onShareAppMessage() {
			var title = this.noteType == 'goods_item' ? this.goodsItemInfo.note_title : this.shopSaidInfo.note_title;
			var imageUrl = this.noteType == 'goods_item' ? this.goodsItemInfo.cover_img : this.shopSaidInfo.cover_img;
			imageUrl = this.$util.img(imageUrl.split(',')[0]);
			var route = this.$util.getCurrentShareRoute(this.memberInfo ? this.memberInfo.member_id : 0);
			var path = route.path;
			return {
				title: title,
				path: path,
				imageUrl: imageUrl
			};
		},
		// 分享到微信朋友圈
		// #ifdef MP-WEIXIN
		onShareTimeline() {
			var title = this.noteType == 'goods_item' ? this.goodsItemInfo.note_title : this.shopSaidInfo.note_title;
			var route = this.$util.getCurrentShareRoute(this.memberInfo ? this.memberInfo.member_id : 0);
			var imageUrl = this.noteType == 'goods_item' ? this.goodsItemInfo.cover_img : this.shopSaidInfo.cover_img;
			imageUrl = this.$util.img(imageUrl.split(',')[0]);
			var query = route.query;
			return {
				title: title,
				query: query,
				imageUrl: imageUrl
			};
		},
		// #endif
		methods: {
			/* 获取笔记详情 */
			getNoteDetail() {
				this.$api.sendRequest({
					url: '/notes/api/notes/detail',
					data: {
						note_id: this.noteId
					},
					success: res => {
						if (res.code == 0 && res.data) {
							this.noteType = res.data.note_type;

							// 设置导航栏标题为笔记标题
							uni.setNavigationBarTitle({
								title: res.data.note_title || (this.noteType == 'goods_item' ? '会客厅详情' : '笔记详情')
							});

							if (this.noteType == 'goods_item') {
								this.goodsItemInfo = res.data;

								// if (this.goodsItemInfo.note_content) this.goodsItemInfo.note_content = htmlParser(this.goodsItemInfo.note_content);
								//获取分享图片
								if (this.goodsItemInfo.goods_image) {
									this.shareImg = this.$util.img(this.goodsItemInfo.goods_image);
								} else {
									this.shareImg = this.$util.getDefaultImage().goods;
								}

								if (this.goodsItemInfo.goods_list.length) {
									this.goodsItemInfo.goods_image = this.goodsItemInfo.goods_list[0].goods_image.split(',')[0];
								} else {
									this.goodsItemInfo.goods_image = this.$util.getDefaultImage().goods;
								}

								if (this.goodsItemInfo.goods_highlights) this.goodsItemInfo.goods_highlights = this.goodsItemInfo.goods_highlights.split(',');
							} else {
								this.shopSaidInfo = res.data;
								// if (this.shopSaidInfo.note_content) this.shopSaidInfo.note_content = htmlParser(this.shopSaidInfo.note_content);
							}
						} else {
							this.$util.redirectTo('/pages_tool/store_notes/note_list', {}, 'redirectTo');
						}

						if (this.$refs.loadingCover) this.$refs.loadingCover.hide();
					}
				});
			},
			/* 点赞 */
			giveLike() {
				if (!this.storeToken) {
					this.$refs.login.open('/pages/index/index');
					return;
				}

				if (this.giveLikeFlag) return false;
				this.giveLikeFlag = true;

				var url = this.giveLikeIdent ? '/notes/api/record/delete' : '/notes/api/record/add';
				this.$api.sendRequest({
					url: url,
					data: {
						note_id: this.noteId
					},
					success: res => {
						this.giveLikeFlag = false;
						if (res.code == 0 && res.data > 0) {
							if (this.noteType != 'goods_item')
								this.shopSaidInfo.dianzan_num = this.giveLikeIdent ? this.shopSaidInfo.dianzan_num - 1 : this.shopSaidInfo.dianzan_num + 1;
							else {
								this.goodsItemInfo.dianzan_num = this.giveLikeIdent ? this.goodsItemInfo.dianzan_num - 1 : this.goodsItemInfo.dianzan_num + 1;
							}
							this.giveLikeIdent = !this.giveLikeIdent;
						} else {
							this.$util.showToast({
								title: res.message
							});
						}
					}
				});
			},
			/* 检测是否点赞 */
			isDianzan() {
				this.$api.sendRequest({
					url: '/notes/api/record/isDianzan',
					data: {
						note_id: this.noteId
					},
					success: res => {
						if (res.code == 0) {
							this.giveLikeIdent = res.data == 1 ? true : false;
						} else {
							this.$util.showToast({
								title: res.message
							});
						}
					}
				});
			},
			/* 页面跳转 */
			redirectToGoods(data, type = '') {
				var id = type ? data : data.goods_list[0].goods_id;

				this.$util.redirectTo('/pages/goods/detail', {
					goods_id: id
				});
			},
			imageError() {
				if (this.goodsItemInfo.goods_image) this.goodsItemInfo.goods_image = this.$util.getDefaultImage().goods;
				this.$forceUpdate();
			},
			/* 显示微信二维码 */
			showWechatQrcode() {
				const qrcodeUrl = this.$util.img(this.goodsItemInfo.wechat_qrcode);
				uni.previewImage({
					current: 0,
					urls: [qrcodeUrl]
				});
			},
			/* 拨打电话 */
			callPhone() {
				uni.showModal({
					title: '提示',
					content: '是否拨打电话：' + this.goodsItemInfo.phone,
					success: (res) => {
						if (res.confirm) {
							uni.makePhoneCall({
								phoneNumber: this.goodsItemInfo.phone
							});
						}
					}
				});
			},
			/* 预约到店体验 */
			makeReservation() {
				this.$util.redirectTo('/pages_tool/store_notes/reservation', {
					note_id: this.noteId
				});
			}
		}
	};
</script>

<style lang="scss">
	page {
		background-color: #fff;
	}

	.goods-detail {
		padding: 30rpx 24rpx 180rpx;

		.said-content,
		.item-content {
			padding: 4rpx;

			rich-text {
				word-wrap: break-word;
			}
		}

		/* 会客厅底部浮层样式 */
		.lounge-actions {
			position: fixed;
			bottom: 0;
			left: 0;
			right: 0;
			display: flex;
			justify-content: space-around;
			align-items: center;
			padding: 20rpx 24rpx;
			background-color: #fff;
			box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.1);
			z-index: 999;

			.action-btn {
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				padding: 15rpx 30rpx;
				border: 2rpx solid #ddd;
				border-radius: 10rpx;
				min-width: 120rpx;

				.iconfont {
					font-size: 40rpx;
					margin-bottom: 8rpx;
					color: #333;
				}

				text {
					font-size: 24rpx;
					color: #333;
				}
			}

			.action-primary {
				flex: 1;
				margin-left: 20rpx;
				background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
				border: none;

				text {
					font-size: 28rpx;
					color: #fff;
					font-weight: bold;
				}
			}
		}

		.goods-item {
			.item-img {
				width: 100%;
				height: 400rpx;
				border-radius: 10rpx;
			}

			.item-title {
				display: block;
				margin: 40rpx 0 44rpx;
				font-size: $font-size-toolbar;
				line-height: 1;
			}

			.item-lightspot text {
				&~text {
					margin-left: 10rpx;
				}

				font-size: $font-size-tag;
				padding: 6rpx 10rpx;
				line-height: 1;
				border-radius: 4rpx;
				color: #ffffff;
			}

			.item-time {
				display: block;
				margin: 44rpx 0 40rpx;
				font-size: $font-size-tag;
				color: #b6b6b6;
			}

			.rest-info {
				text {
					text {
						margin-left: 8rpx;
					}
				}

				text.iconfont {
					font-size: 26rpx;
				}

				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-top: 40rpx;
				color: #6b6b6b;
				font-size: $font-size-tag;
			}

			.item-action {
				.action-left {
					display: flex;

					text {
						&.active {
							margin: 0;
							background-color: rgb(255, 255, 255) !important;
							border: 2rpx solid #dddddd;
						}

						display: flex;
						justify-content: center;
						align-items: center;
						width: 70rpx;
						height: 70rpx;
						background-color: rgba(0, 0, 0, 0.4) !important;
						border-radius: 50%;
						border: 2rpx solid rgba(0, 0, 0, 0);
					}

					button {
						display: flex;
						justify-content: center;
						align-items: center;
						margin: 0;
						margin-left: 16rpx;
						padding: 0;
						width: 70rpx;
						height: 70rpx;
						background-color: rgba(0, 0, 0, 0.4) !important;
						border-radius: 50%;
					}

					.iconfont {
						color: #fff;
						font-size: $font-size-toolbar;
					}
				}

				.action-right {
					margin: 0;
					width: 180rpx;
					height: 70rpx;
					color: #fff;
				}

				position: fixed;
				bottom: 0;
				width: calc(100% - 24px);
				display: flex;
				margin: 80rpx 0;
				justify-content: space-between;
			}
		}

		.shop-said {
			.said-title {
				display: block;
				margin: 40rpx 0 44rpx;
				font-size: $font-size-toolbar;
				line-height: 1;
			}

			.said-time {
				display: block;
				margin: 44rpx 0 40rpx;
				font-size: $font-size-tag;
				color: #b6b6b6;
			}

			.rest-info {
				text {
					text {
						margin-left: 8rpx;
					}
				}

				text.iconfont {
					font-size: 26rpx;
				}

				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-top: 40rpx;
				color: #6b6b6b;
				font-size: $font-size-tag;
			}

			.said-action {
				position: fixed;
				bottom: 0;
				width: calc(100% - 24px);
				display: flex;
				justify-content: center;
				margin: 80rpx 0;

				text {
					&.active {
						margin: 0;
						background-color: rgb(255, 255, 255) !important;
						border: 2rpx solid #dddddd;
					}

					display: flex;
					justify-content: center;
					align-items: center;
					width: 70rpx;
					height: 70rpx;
					background-color: rgba(0, 0, 0, 0.4) !important;
					border-radius: 50%;
				}

				button {
					display: flex;
					justify-content: center;
					align-items: center;
					margin: 0;
					margin-left: 16rpx;
					padding: 0;
					width: 70rpx;
					height: 70rpx;
					background-color: rgba(0, 0, 0, 0.4) !important;
					border-radius: 50%;
				}

				.iconfont {
					color: #fff;
					font-size: $font-size-toolbar;
				}
			}

			.said-goods {
				display: flex;
				flex-wrap: wrap;
				justify-content: space-between;
				margin-top: 40rpx;

				.commodity-item {
					display: flex;
					flex-direction: column;
					margin-bottom: 22rpx;
					width: 338rpx;
					border: 2rpx solid #f1f1f1;

					image {
						width: 338rpx;
						height: 338rpx;
					}

					.commodity-content {
						padding: 20rpx;

						.commodity-name {
							overflow: hidden;
							display: block;
							font-size: $font-size-tag;
							text-overflow: ellipsis;
							white-space: nowrap;
							color: #383838;
						}

						.commodity-price {
							display: block;
							font-size: $font-size-tag;
						}
					}
				}
			}
		}
	}
</style>