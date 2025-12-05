<template>
	<page-meta :page-style="themeColor"></page-meta>
	<scroll-view scroll-y="true" class="container">
		<view class="apply-page">
			<!-- 头部提示 -->
			<view class="header-tip">
				<text class="tip-icon">🎉</text>
				<text class="tip-text">{{ inviterInfo.has_quota ? '恭喜!您受邀成为特邀会员' : '特邀会员申请' }}</text>
			</view>

			<!-- 邀请人信息 -->
			<view class="inviter-card" v-if="inviterInfo.inviter_nickname">
				<view class="card-title">邀请人信息</view>
				<view class="inviter-info">
					<view class="info-item">
						<text class="label">邀请人</text>
						<text class="value">{{ inviterInfo.inviter_nickname }}</text>
					</view>
					<view class="info-item">
						<text class="label">等级</text>
						<text class="value vip">{{ inviterInfo.inviter_level_name }}</text>
					</view>
					<view class="info-item">
						<text class="label">剩余名额</text>
						<text class="value highlight">{{ inviterInfo.available_quota }}</text>
					</view>
				</view>
			</view>

			<!-- 提示：推荐人不是特邀会员或无名额 -->
			<view class="warning-tip" v-if="showNoInviterWarning">
				<text class="icon">⚠️</text>
				<text>{{ noInviterWarningText }}</text>
			</view>

			<!-- 名额已用完提示 -->
			<view class="quota-empty-tip" v-if="inviterInfo.inviter_nickname && !inviterInfo.has_quota">
				<text class="icon">⚠️</text>
				<text>邀请人的名额已用完，暂时无法申请特邀会员</text>
			</view>

			<!-- 申请表单 -->
			<view class="form-card" v-if="inviterInfo.has_quota && !hasExistApplication">
				<view class="card-title">填写申请信息</view>
				<view class="form-item">
					<view class="form-label">真实姓名 <text class="required">*</text></view>
					<input type="text"
						v-model="formData.realname"
						placeholder="请输入真实姓名"
						class="form-input"
						maxlength="20" />
				</view>

				<view class="form-tip">
					<text>📋 申请须知：</text>
					<text>1. 提交申请后需等待审核</text>
					<text>2. 审核通过后将升级为特邀会员</text>
					<text>3. 审核期间将占用邀请人名额</text>
				</view>

				<button class="submit-btn" @click="submitApplication">提交申请</button>
			</view>

			<!-- 已有申请提示 -->
			<view class="exist-application" v-if="hasExistApplication">
				<view class="icon">⏰</view>
				<text class="text">您已有待审核的申请，请耐心等待</text>
				<button class="check-btn" @click="checkApplicationStatus">查看申请状态</button>
			</view>
		</view>
	</scroll-view>
</template>

<script>
export default {
	data() {
		return {
			inviterId: 0, // 邀请人ID
			fromUrlParam: false, // 是否从URL参数获取的inviter_id
			inviterInfo: {
				has_quota: false,
				available_quota: 0,
				inviter_nickname: '',
				inviter_level_name: ''
			},
			formData: {
				realname: ''
			},
			hasExistApplication: false,
			showNoInviterWarning: false, // 显示无推荐人警告
			noInviterWarningText: '' // 警告文本
		};
	},
	onLoad(options) {
		// 检查是否有URL参数传入的inviter_id
		if (options.inviter_id) {
			this.inviterId = parseInt(options.inviter_id);
			this.fromUrlParam = true;
			this.checkInviterQuota();
			this.checkExistApplication();
		} else {
			// 没有URL参数，从当前用户的source_member获取
			this.getMySourceMember();
		}
	},
	methods: {
		/**
		 * 获取当前用户的source_member作为邀请人
		 */
		getMySourceMember() {
			this.$api.sendRequest({
				url: '/api/membervip/getMySourceMember',
				success: res => {
					if (res.code >= 0) {
						if (res.data.source_member && res.data.source_member > 0) {
							this.inviterId = res.data.source_member;
							this.fromUrlParam = false;
							this.checkInviterQuota();
							this.checkExistApplication();
						} else {
							// 没有推荐人
							this.showNoInviterWarning = true;
							this.noInviterWarningText = '您当前没有推荐人，无法申请特邀会员';
						}
					} else {
						this.$util.showToast({ title: res.message });
					}
				}
			});
		},

		/**
		 * 检查邀请人名额
		 */
		checkInviterQuota() {
			this.$api.sendRequest({
				url: '/api/membervip/checkInviterQuota',
				data: {
					inviter_id: this.inviterId
				},
				success: res => {
					if (res.code >= 0) {
						this.inviterInfo = res.data;
						if (!res.data.has_quota) {
							this.showNoInviterWarning = true;
							if (res.data.inviter_nickname) {
								this.noInviterWarningText = `推荐人 ${res.data.inviter_nickname} 的名额已用完`;
							}
						}
					} else {
						// 推荐人不是特邀会员或其他错误
						this.showNoInviterWarning = true;
						this.noInviterWarningText = res.message || '推荐人不是特邀会员或名额已用完';
					}
				}
			});
		},

		/**
		 * 检查是否有待审核的申请
		 */
		checkExistApplication() {
			this.$api.sendRequest({
				url: '/api/membervip/getApplicationStatus',
				success: res => {
					if (res.code >= 0) {
						if (res.data.has_application && res.data.status === 0) {
							this.hasExistApplication = true;
						}
					}
				}
			});
		},

		/**
		 * 提交申请
		 */
		submitApplication() {
			// 验证表单
			if (!this.formData.realname || this.formData.realname.trim() === '') {
				this.$util.showToast({ title: '请填写真实姓名' });
				return;
			}

			uni.showLoading({ title: '提交中...' });

			this.$api.sendRequest({
				url: '/api/membervip/applyVipMember',
				data: {
					inviter_id: this.inviterId,
					realname: this.formData.realname,
					update_source_member: this.fromUrlParam ? 1 : 0 // 如果是URL参数，需要更新source_member
				},
				success: res => {
					uni.hideLoading();
					if (res.code >= 0) {
						uni.showModal({
							title: '提交成功',
							content: '您的申请已提交，请等待审核',
							showCancel: false,
							success: () => {
								// 跳转到申请状态页
								this.$util.redirectTo('/pages_tool/member/vip_status');
							}
						});
					} else if (res.message === 'QUOTA_EXHAUSTED') {
						// 名额用完
						uni.showModal({
							title: '提示',
							content: '邀请人的名额已用完，暂时无法申请',
							showCancel: false,
							success: () => {
								uni.navigateBack();
							}
						});
					} else {
						this.$util.showToast({ title: res.message });
					}
				},
				fail: () => {
					uni.hideLoading();
					this.$util.showToast({ title: '提交失败，请重试' });
				}
			});
		},

		/**
		 * 查看申请状态
		 */
		checkApplicationStatus() {
			this.$util.redirectTo('/pages_tool/member/vip_status');
		}
	}
};
</script>

<style lang="scss" scoped>
.container {
	height: 100vh;
	background: #f5f5f5;
}

.apply-page {
	padding: 20rpx;
}

/* 头部提示 */
.header-tip {
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	border-radius: 20rpx;
	padding: 40rpx;
	text-align: center;
	color: #fff;
	margin-bottom: 20rpx;

	.tip-icon {
		font-size: 60rpx;
		display: block;
		margin-bottom: 15rpx;
	}

	.tip-text {
		font-size: 32rpx;
		font-weight: bold;
	}
}

/* 邀请人信息卡片 */
.inviter-card {
	background: #fff;
	border-radius: 20rpx;
	padding: 40rpx;
	margin-bottom: 20rpx;

	.card-title {
		font-size: 32rpx;
		font-weight: bold;
		margin-bottom: 30rpx;
		color: #333;
	}

	.inviter-info {
		.info-item {
			display: flex;
			justify-content: space-between;
			padding: 20rpx 0;
			border-bottom: 1rpx solid #f0f0f0;

			&:last-child {
				border-bottom: none;
			}

			.label {
				font-size: 28rpx;
				color: #666;
			}

			.value {
				font-size: 28rpx;
				color: #333;
				font-weight: 500;

				&.vip {
					color: #FFD700;
				}

				&.highlight {
					color: #667eea;
					font-weight: bold;
					font-size: 32rpx;
				}
			}
		}
	}
}

/* 警告提示（没有推荐人或推荐人不是特邀会员） */
.warning-tip {
	background: #fff3cd;
	border-radius: 20rpx;
	padding: 30rpx;
	text-align: center;
	color: #856404;
	margin-bottom: 20rpx;

	.icon {
		font-size: 48rpx;
		display: block;
		margin-bottom: 10rpx;
	}
}

/* 名额用完提示 */
.quota-empty-tip {
	background: #fff3cd;
	border-radius: 20rpx;
	padding: 30rpx;
	text-align: center;
	color: #856404;
	margin-bottom: 20rpx;

	.icon {
		font-size: 48rpx;
		display: block;
		margin-bottom: 10rpx;
	}
}

/* 表单卡片 */
.form-card {
	background: #fff;
	border-radius: 20rpx;
	padding: 40rpx;
	margin-bottom: 20rpx;

	.card-title {
		font-size: 32rpx;
		font-weight: bold;
		margin-bottom: 30rpx;
		color: #333;
	}

	.form-item {
		margin-bottom: 30rpx;

		.form-label {
			font-size: 28rpx;
			color: #333;
			margin-bottom: 15rpx;

			.required {
				color: #f56c6c;
				margin-left: 5rpx;
			}
		}

		.form-input {
			width: 100%;
			height: 80rpx;
			background: #f5f5f5;
			border-radius: 12rpx;
			padding: 0 20rpx;
			font-size: 28rpx;
		}
	}

	.form-tip {
		background: #f0f7ff;
		border-radius: 12rpx;
		padding: 25rpx;
		margin-bottom: 30rpx;

		text {
			display: block;
			font-size: 24rpx;
			color: #666;
			line-height: 40rpx;

			&:first-child {
				font-weight: bold;
				margin-bottom: 10rpx;
				color: #333;
			}
		}
	}

	.submit-btn {
		background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
		color: #fff;
		border-radius: 50rpx;
		height: 90rpx;
		line-height: 90rpx;
		font-size: 32rpx;
		border: none;

		&::after {
			border: none;
		}
	}
}

/* 已有申请提示 */
.exist-application {
	background: #fff;
	border-radius: 20rpx;
	padding: 60rpx 40rpx;
	text-align: center;

	.icon {
		font-size: 100rpx;
		display: block;
		margin-bottom: 20rpx;
	}

	.text {
		display: block;
		font-size: 28rpx;
		color: #666;
		margin-bottom: 40rpx;
	}

	.check-btn {
		background: #667eea;
		color: #fff;
		border-radius: 50rpx;
		height: 80rpx;
		line-height: 80rpx;
		font-size: 28rpx;
		border: none;

		&::after {
			border: none;
		}
	}
}
</style>
