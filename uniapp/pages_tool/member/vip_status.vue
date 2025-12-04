<template>
	<page-meta :page-style="themeColor"></page-meta>
	<scroll-view scroll-y="true" class="container">
		<view class="status-page">
			<!-- 待审核状态 -->
			<view class="status-card pending" v-if="application.status === 0">
				<view class="status-icon">⏰</view>
				<view class="status-title">审核中</view>
				<view class="status-desc">您的申请正在审核中，请耐心等待</view>

				<view class="detail-info">
					<view class="info-item">
						<text class="label">申请时间</text>
						<text class="value">{{ formatTime(application.create_time) }}</text>
					</view>
					<view class="info-item">
						<text class="label">真实姓名</text>
						<text class="value">{{ application.realname }}</text>
					</view>
					<view class="info-item">
						<text class="label">邀请人</text>
						<text class="value">{{ application.inviter_nickname }}</text>
					</view>
				</view>
			</view>

			<!-- 审核通过状态 -->
			<view class="status-card success" v-else-if="application.status === 1">
				<view class="status-icon">✅</view>
				<view class="status-title">审核通过</view>
				<view class="status-desc">恭喜您，已成为特邀会员！</view>

				<view class="detail-info">
					<view class="info-item">
						<text class="label">审核时间</text>
						<text class="value">{{ formatTime(application.audit_time) }}</text>
					</view>
					<view class="info-item">
						<text class="label">真实姓名</text>
						<text class="value">{{ application.realname }}</text>
					</view>
					<view class="info-item">
						<text class="label">邀请人</text>
						<text class="value">{{ application.inviter_nickname }}</text>
					</view>
				</view>

				<button class="action-btn success" @click="goToPromote">查看我的推广</button>
			</view>

			<!-- 审核拒绝状态 -->
			<view class="status-card reject" v-else-if="application.status === -1">
				<view class="status-icon">❌</view>
				<view class="status-title">审核未通过</view>
				<view class="status-desc">很抱歉，您的申请未通过审核</view>

				<view class="detail-info">
					<view class="info-item">
						<text class="label">审核时间</text>
						<text class="value">{{ formatTime(application.audit_time) }}</text>
					</view>
					<view class="info-item">
						<text class="label">拒绝原因</text>
						<text class="value reject-reason">{{ application.audit_remark || '无' }}</text>
					</view>
				</view>

				<button class="action-btn" @click="goToMemberCenter">返回会员中心</button>
			</view>

			<!-- 无申请记录 -->
			<view class="status-card empty" v-if="!application.has_application">
				<view class="status-icon">📋</view>
				<view class="status-title">暂无申请记录</view>
				<view class="status-desc">您还没有提交过特邀会员申请</view>

				<button class="action-btn" @click="goToMemberCenter">返回会员中心</button>
			</view>
		</view>
	</scroll-view>
</template>

<script>
export default {
	data() {
		return {
			application: {
				has_application: false,
				status: 0,
				realname: '',
				inviter_nickname: '',
				create_time: 0,
				audit_time: 0,
				audit_remark: ''
			}
		};
	},
	onLoad() {
		this.loadApplicationStatus();
	},
	methods: {
		/**
		 * 加载申请状态
		 */
		loadApplicationStatus() {
			uni.showLoading({ title: '加载中...' });

			this.$api.sendRequest({
				url: '/api/membervip/getApplicationStatus',
				success: res => {
					uni.hideLoading();
					if (res.code >= 0) {
						this.application = res.data;
					} else {
						this.$util.showToast({ title: res.message });
					}
				},
				fail: () => {
					uni.hideLoading();
					this.$util.showToast({ title: '加载失败，请重试' });
				}
			});
		},

		/**
		 * 格式化时间
		 */
		formatTime(timestamp) {
			if (!timestamp) return '-';
			return this.$util.formatTime(timestamp, 'Y-m-d H:i');
		},

		/**
		 * 前往推广页面
		 */
		goToPromote() {
			this.$util.redirectTo('/pages_tool/member/promote');
		},

		/**
		 * 返回会员中心
		 */
		goToMemberCenter() {
			uni.navigateBack();
		}
	}
};
</script>

<style lang="scss" scoped>
.container {
	height: 100vh;
	background: #f5f5f5;
}

.status-page {
	padding: 40rpx 20rpx;
}

.status-card {
	background: #fff;
	border-radius: 30rpx;
	padding: 60rpx 40rpx;
	text-align: center;

	.status-icon {
		font-size: 120rpx;
		margin-bottom: 30rpx;
	}

	.status-title {
		font-size: 40rpx;
		font-weight: bold;
		margin-bottom: 20rpx;
		color: #333;
	}

	.status-desc {
		font-size: 28rpx;
		color: #666;
		margin-bottom: 40rpx;
	}

	.detail-info {
		background: #f8f9fa;
		border-radius: 20rpx;
		padding: 30rpx;
		margin-bottom: 40rpx;
		text-align: left;

		.info-item {
			display: flex;
			justify-content: space-between;
			padding: 20rpx 0;
			border-bottom: 1rpx solid #eee;

			&:last-child {
				border-bottom: none;
			}

			.label {
				font-size: 28rpx;
				color: #999;
			}

			.value {
				font-size: 28rpx;
				color: #333;
				font-weight: 500;
				max-width: 60%;
				text-align: right;

				&.reject-reason {
					color: #f56c6c;
				}
			}
		}
	}

	.action-btn {
		background: #667eea;
		color: #fff;
		border-radius: 50rpx;
		height: 90rpx;
		line-height: 90rpx;
		font-size: 32rpx;
		border: none;

		&::after {
			border: none;
		}

		&.success {
			background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
		}
	}

	&.pending {
		border-top: 6rpx solid #ffa726;

		.status-icon {
			animation: pulse 1.5s infinite;
		}
	}

	&.success {
		border-top: 6rpx solid #4ade80;
	}

	&.reject {
		border-top: 6rpx solid #f56c6c;
	}

	&.empty {
		border-top: 6rpx solid #ccc;
	}
}

@keyframes pulse {
	0%, 100% {
		transform: scale(1);
	}
	50% {
		transform: scale(1.1);
	}
}
</style>
