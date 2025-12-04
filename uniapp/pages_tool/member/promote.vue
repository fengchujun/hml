<template>
	<page-meta :page-style="themeColor"></page-meta>
	<scroll-view scroll-y="true" class="container">
		<view class="promote-page">
			<!-- 头部信息卡片 -->
			<view class="header-card">
				<view class="member-info">
					<text class="title">我的推广</text>
					<view class="level-tag" :class="{'vip': memberInfo.is_vip}">
						{{ memberInfo.member_level_name }}
					</view>
				</view>

				<!-- 特邀会员专属信息 -->
				<view v-if="memberInfo.is_vip" class="vip-info-section">
					<!-- 保级进度 -->
					<view class="preserve-section">
						<view class="section-title">
							<text class="icon">🏆</text>
							<text>保级进度</text>
						</view>
						<view class="preserve-progress">
							<view class="progress-bar">
								<view class="progress-fill" :style="{width: preserveInfo.preserve_progress + '%'}"></view>
							</view>
							<view class="progress-text">
								<text>已消费：¥{{ preserveInfo.year_consumption }}</text>
								<text>目标：¥{{ preserveInfo.preserve_target }}</text>
							</view>
							<view class="progress-tip" v-if="preserveInfo.need_amount > 0">
								还需消费 <text class="highlight">¥{{ preserveInfo.need_amount }}</text> 即可保级
							</view>
							<view class="progress-tip success" v-else>
								恭喜！已达到保级标准
							</view>
						</view>
					</view>

					<!-- 邀请名额 -->
					<view class="quota-section">
						<view class="section-title">
							<text class="icon">🎫</text>
							<text>邀请名额</text>
						</view>
						<view class="quota-cards">
							<view class="quota-card">
								<text class="num">{{ quotaInfo.total_quota }}</text>
								<text class="label">总名额</text>
							</view>
							<view class="quota-card used">
								<text class="num">{{ quotaInfo.used_quota }}</text>
								<text class="label">已使用</text>
							</view>
							<view class="quota-card locked">
								<text class="num">{{ quotaInfo.locked_quota }}</text>
								<text class="label">审核中</text>
							</view>
							<view class="quota-card available">
								<text class="num">{{ quotaInfo.available_quota }}</text>
								<text class="label">可用</text>
							</view>
						</view>
						<view class="quota-tip" v-if="quotaInfo.available_quota === 0">
							💡 消费满5万元可获得2个推荐名额
						</view>
					</view>
				</view>
			</view>

			<!-- 推广统计 -->
			<view class="stats-card">
				<view class="section-title">
					<text class="icon">📊</text>
					<text>推广统计</text>
				</view>
				<view class="stats-grid">
					<view class="stat-item">
						<text class="num">{{ stats.total_count }}</text>
						<text class="label">累计推荐</text>
					</view>
					<view class="stat-item" v-if="memberInfo.is_vip">
						<text class="num">{{ stats.vip_member_count }}</text>
						<text class="label">特邀会员</text>
					</view>
					<view class="stat-item">
						<text class="num">{{ stats.normal_member_count }}</text>
						<text class="label">普通会员</text>
					</view>
				</view>
			</view>

			<!-- 推广工具 -->
			<view class="tools-card">
				<view class="section-title">
					<text class="icon">🎁</text>
					<text>推广工具</text>
				</view>

				<!-- 小程序码 -->
				<view class="qrcode-section">
					<view class="qrcode-box">
						<image v-if="memberInfo.share_qrcode"
							:src="$util.img(memberInfo.share_qrcode)"
							mode="aspectFit"
							class="qrcode-img"></image>
						<view v-else class="qrcode-loading">
							<text>生成中...</text>
						</view>
					</view>
					<view class="qrcode-tip">
						<text>长按保存小程序码，分享给好友扫码</text>
					</view>
				</view>

				<!-- 引导图片（预留位置，您自己添加） -->
				<view class="guide-image-section">
					<image src="/static/images/promote_guide.png"
						mode="widthFix"
						class="guide-img"
						v-if="false"></image>
					<!-- TODO: 您可以在这里添加引导转发的图片 -->
				</view>

				<!-- 分享按钮 -->
				<button open-type="share" class="share-btn">
					<text class="iconfont icon-share"></text>
					<text>立即分享给好友</text>
				</button>
			</view>

			<!-- 推荐会员列表 -->
			<view class="members-card" v-if="recommendedMembers.length > 0">
				<view class="section-title">
					<text class="icon">👥</text>
					<text>推荐会员</text>
					<text class="count">（{{ stats.total_count }}人）</text>
				</view>
				<view class="members-list">
					<view class="member-item" v-for="(member, index) in recommendedMembers" :key="member.member_id">
						<image :src="$util.img(member.headimg || 'public/uniapp/default_head.png')"
							class="member-avatar"
							mode="aspectFill"></image>
						<view class="member-info">
							<text class="member-name">{{ member.nickname }}</text>
							<text class="member-level" :class="{'vip': member.member_level == 2}">
								{{ member.member_level_name }}
							</text>
						</view>
						<text class="member-time">{{ $util.formatTime(member.reg_time, 'Y-m-d') }}</text>
					</view>
				</view>
			</view>

			<!-- 普通会员引导卡片 -->
			<view class="guide-card" v-if="!memberInfo.is_vip">
				<view class="guide-content">
					<text class="guide-title">🌟 升级特邀会员</text>
					<text class="guide-desc">通过特邀会员邀请，成为特邀会员，享受更多权益！</text>
				</view>
			</view>
		</view>
	</scroll-view>
</template>

<script>
export default {
	data() {
		return {
			memberInfo: {
				is_vip: false,
				member_level_name: '普通会员',
				share_qrcode: ''
			},
			quotaInfo: {
				total_quota: 0,
				used_quota: 0,
				locked_quota: 0,
				available_quota: 0,
				quota_expire_time: 0
			},
			preserveInfo: {
				year_consumption: 0,
				preserve_target: 50000,
				preserve_progress: 0,
				need_amount: 50000
			},
			stats: {
				total_count: 0,
				vip_member_count: 0,
				normal_member_count: 0
			},
			recommendedMembers: []
		};
	},
	onLoad() {
		this.loadPromoteData();
	},
	onShareAppMessage() {
		// 分享时携带推荐人ID
		let path = '/pages/index/index';
		if (this.$store.state.memberInfo && this.$store.state.memberInfo.member_id) {
			path += '?source_member=' + this.$store.state.memberInfo.member_id;
		}

		return {
			title: '发现一个好东西，分享给你~',
			path: path,
			imageUrl: this.memberInfo.share_qrcode ? this.$util.img(this.memberInfo.share_qrcode) : ''
		};
	},
	methods: {
		/**
		 * 加载推广数据
		 */
		loadPromoteData() {
			uni.showLoading({ title: '加载中...' });

			this.$api.sendRequest({
				url: '/api/membervip/getPromoteStats',
				success: res => {
					uni.hideLoading();
					if (res.code >= 0) {
						this.memberInfo = res.data.member_info;
						this.quotaInfo = res.data.quota_info;
						this.preserveInfo = res.data.preserve_info;
						this.stats = res.data.stats;
						this.recommendedMembers = res.data.recommended_members || [];
					} else {
						this.$util.showToast({ title: res.message });
					}
				},
				fail: () => {
					uni.hideLoading();
					this.$util.showToast({ title: '加载失败，请重试' });
				}
			});
		}
	}
};
</script>

<style lang="scss" scoped>
.container {
	height: 100vh;
	background: #f5f5f5;
}

.promote-page {
	padding: 20rpx;
}

/* 头部信息卡片 */
.header-card {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 20rpx;
	padding: 40rpx;
	color: #fff;
	margin-bottom: 20rpx;

	.member-info {
		display: flex;
		justify-content: space-between;
		align-items: center;
		margin-bottom: 40rpx;

		.title {
			font-size: 36rpx;
			font-weight: bold;
		}

		.level-tag {
			background: rgba(255, 255, 255, 0.3);
			padding: 10rpx 20rpx;
			border-radius: 30rpx;
			font-size: 24rpx;

			&.vip {
				background: #FFD700;
				color: #333;
			}
		}
	}

	.vip-info-section {
		.preserve-section, .quota-section {
			background: rgba(255, 255, 255, 0.15);
			border-radius: 16rpx;
			padding: 30rpx;
			margin-bottom: 20rpx;

			.section-title {
				display: flex;
				align-items: center;
				font-size: 28rpx;
				font-weight: bold;
				margin-bottom: 20rpx;

				.icon {
					margin-right: 10rpx;
					font-size: 32rpx;
				}
			}
		}

		.preserve-progress {
			.progress-bar {
				height: 16rpx;
				background: rgba(255, 255, 255, 0.3);
				border-radius: 8rpx;
				overflow: hidden;
				margin-bottom: 15rpx;

				.progress-fill {
					height: 100%;
					background: #4ade80;
					border-radius: 8rpx;
					transition: width 0.3s;
				}
			}

			.progress-text {
				display: flex;
				justify-content: space-between;
				font-size: 24rpx;
				margin-bottom: 10rpx;
			}

			.progress-tip {
				font-size: 24rpx;
				text-align: center;

				.highlight {
					color: #FFD700;
					font-weight: bold;
				}

				&.success {
					color: #4ade80;
				}
			}
		}

		.quota-cards {
			display: flex;
			justify-content: space-between;

			.quota-card {
				flex: 1;
				text-align: center;
				padding: 20rpx 10rpx;
				background: rgba(255, 255, 255, 0.2);
				border-radius: 12rpx;
				margin: 0 10rpx;

				.num {
					display: block;
					font-size: 48rpx;
					font-weight: bold;
					margin-bottom: 8rpx;
				}

				.label {
					font-size: 22rpx;
					opacity: 0.9;
				}

				&:first-child {
					margin-left: 0;
				}

				&:last-child {
					margin-right: 0;
				}

				&.available {
					background: rgba(74, 222, 128, 0.3);
				}
			}
		}

		.quota-tip {
			margin-top: 15rpx;
			font-size: 24rpx;
			text-align: center;
			opacity: 0.9;
		}
	}
}

/* 统计卡片 */
.stats-card {
	background: #fff;
	border-radius: 20rpx;
	padding: 40rpx;
	margin-bottom: 20rpx;

	.section-title {
		display: flex;
		align-items: center;
		font-size: 32rpx;
		font-weight: bold;
		margin-bottom: 30rpx;
		color: #333;

		.icon {
			margin-right: 10rpx;
			font-size: 36rpx;
		}

		.count {
			margin-left: 10rpx;
			font-size: 24rpx;
			color: #999;
			font-weight: normal;
		}
	}

	.stats-grid {
		display: flex;
		justify-content: space-around;

		.stat-item {
			text-align: center;

			.num {
				display: block;
				font-size: 56rpx;
				font-weight: bold;
				color: #667eea;
				margin-bottom: 10rpx;
			}

			.label {
				font-size: 26rpx;
				color: #666;
			}
		}
	}
}

/* 推广工具卡片 */
.tools-card {
	background: #fff;
	border-radius: 20rpx;
	padding: 40rpx;
	margin-bottom: 20rpx;

	.section-title {
		display: flex;
		align-items: center;
		font-size: 32rpx;
		font-weight: bold;
		margin-bottom: 30rpx;
		color: #333;

		.icon {
			margin-right: 10rpx;
			font-size: 36rpx;
		}
	}

	.qrcode-section {
		text-align: center;
		margin-bottom: 30rpx;

		.qrcode-box {
			width: 400rpx;
			height: 400rpx;
			margin: 0 auto 20rpx;
			border: 2rpx solid #eee;
			border-radius: 12rpx;
			overflow: hidden;
			display: flex;
			align-items: center;
			justify-content: center;

			.qrcode-img {
				width: 100%;
				height: 100%;
			}

			.qrcode-loading {
				color: #999;
				font-size: 28rpx;
			}
		}

		.qrcode-tip {
			font-size: 24rpx;
			color: #999;
		}
	}

	.guide-image-section {
		margin-bottom: 30rpx;

		.guide-img {
			width: 100%;
			border-radius: 12rpx;
		}
	}

	.share-btn {
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		color: #fff;
		border-radius: 50rpx;
		height: 90rpx;
		line-height: 90rpx;
		font-size: 32rpx;
		display: flex;
		align-items: center;
		justify-content: center;
		border: none;

		.iconfont {
			margin-right: 10rpx;
			font-size: 36rpx;
		}

		&::after {
			border: none;
		}
	}
}

/* 推荐会员列表 */
.members-card {
	background: #fff;
	border-radius: 20rpx;
	padding: 40rpx;
	margin-bottom: 20rpx;

	.section-title {
		display: flex;
		align-items: center;
		font-size: 32rpx;
		font-weight: bold;
		margin-bottom: 30rpx;
		color: #333;

		.icon {
			margin-right: 10rpx;
			font-size: 36rpx;
		}

		.count {
			margin-left: 10rpx;
			font-size: 24rpx;
			color: #999;
			font-weight: normal;
		}
	}

	.members-list {
		.member-item {
			display: flex;
			align-items: center;
			padding: 20rpx 0;
			border-bottom: 1rpx solid #f0f0f0;

			&:last-child {
				border-bottom: none;
			}

			.member-avatar {
				width: 80rpx;
				height: 80rpx;
				border-radius: 50%;
				margin-right: 20rpx;
			}

			.member-info {
				flex: 1;

				.member-name {
					display: block;
					font-size: 28rpx;
					color: #333;
					margin-bottom: 8rpx;
				}

				.member-level {
					display: inline-block;
					font-size: 22rpx;
					color: #666;
					background: #f0f0f0;
					padding: 4rpx 12rpx;
					border-radius: 10rpx;

					&.vip {
						background: #FFD700;
						color: #333;
					}
				}
			}

			.member-time {
				font-size: 24rpx;
				color: #999;
			}
		}
	}
}

/* 引导卡片 */
.guide-card {
	background: linear-gradient(135deg, #ffa726 0%, #ff5722 100%);
	border-radius: 20rpx;
	padding: 40rpx;
	text-align: center;
	color: #fff;

	.guide-content {
		.guide-title {
			display: block;
			font-size: 36rpx;
			font-weight: bold;
			margin-bottom: 15rpx;
		}

		.guide-desc {
			font-size: 26rpx;
			opacity: 0.9;
		}
	}
}
</style>
