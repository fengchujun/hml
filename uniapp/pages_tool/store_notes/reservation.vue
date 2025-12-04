<template>
	<page-meta :page-style="themeColor"></page-meta>
	<view class="reservation-page">
		<view class="form-container">
			<view class="form-title">预约到店体验</view>
			<view class="lounge-title" v-if="noteTitle">{{ noteTitle }}</view>

			<view class="form-item">
				<view class="label"><text class="required">*</text>姓名</view>
				<input type="text" v-model="formData.name" placeholder="请输入姓名" class="input" />
			</view>

			<view class="form-item">
				<view class="label"><text class="required">*</text>手机</view>
				<input type="number" v-model="formData.phone" placeholder="请输入手机号" class="input" />
			</view>

			<view class="form-item">
				<view class="label"><text class="required">*</text>留言</view>
				<textarea v-model="formData.message" placeholder="请输入留言备注" class="textarea" maxlength="200"></textarea>
				<view class="char-count">{{ formData.message.length }}/200</view>
			</view>

			<button class="submit-btn" @click="submitReservation" :disabled="submitting">
				{{ submitting ? '提交中...' : '确认提交预约' }}
			</button>
		</view>
	</view>
</template>

<script>
	export default {
		data() {
			return {
				noteId: '',
				noteTitle: '',
				formData: {
					name: '',
					phone: '',
					message: ''
				},
				submitting: false
			};
		},
		onLoad(options) {
			if (options.note_id) {
				this.noteId = options.note_id;
				this.getNoteInfo();
			}
		},
		onShow() {
			// 检查用户是否已登录
			if (!this.storeToken) {
				uni.showModal({
					title: '提示',
					content: '请先登录后再进行预约',
					showCancel: false,
					success: () => {
						uni.navigateBack();
					}
				});
			}
		},
		methods: {
			/* 获取会客厅信息 */
			getNoteInfo() {
				this.$api.sendRequest({
					url: '/notes/api/notes/detail',
					data: {
						note_id: this.noteId
					},
					success: res => {
						if (res.code == 0 && res.data) {
							this.noteTitle = res.data.note_title;
							// 设置导航栏标题
							uni.setNavigationBarTitle({
								title: '预约 - ' + this.noteTitle
							});
						}
					}
				});
			},

			/* 验证表单 */
			validateForm() {
				if (!this.formData.name || !this.formData.name.trim()) {
					this.$util.showToast({ title: '请输入姓名' });
					return false;
				}

				if (!this.formData.phone || !this.formData.phone.trim()) {
					this.$util.showToast({ title: '请输入手机号' });
					return false;
				}

				// 验证手机号格式
				const phoneReg = /^1[3-9]\d{9}$/;
				if (!phoneReg.test(this.formData.phone)) {
					this.$util.showToast({ title: '请输入正确的手机号' });
					return false;
				}

				if (!this.formData.message || !this.formData.message.trim()) {
					this.$util.showToast({ title: '请输入留言备注' });
					return false;
				}

				return true;
			},

			/* 提交预约 */
			submitReservation() {
				if (!this.validateForm()) {
					return;
				}

				if (this.submitting) return;

				this.submitting = true;

				this.$api.sendRequest({
					url: '/notes/api/reservation/add',
					data: {
						note_id: this.noteId,
						name: this.formData.name.trim(),
						phone: this.formData.phone.trim(),
						message: this.formData.message.trim()
					},
					success: res => {
						this.submitting = false;
						if (res.code == 0) {
							uni.showModal({
								title: '预约成功',
								content: '预约成功，等待客服联系',
								showCancel: false,
								success: () => {
									// 返回上一页
									uni.navigateBack();
								}
							});
						} else {
							this.$util.showToast({
								title: res.message || '预约失败，请稍后重试'
							});
						}
					},
					fail: () => {
						this.submitting = false;
						this.$util.showToast({
							title: '网络错误，请稍后重试'
						});
					}
				});
			}
		}
	};
</script>

<style lang="scss" scoped>
	page {
		background-color: #f5f5f5;
	}

	.reservation-page {
		min-height: 100vh;
		padding: 30rpx;

		.form-container {
			background-color: #fff;
			border-radius: 20rpx;
			padding: 40rpx 30rpx;

			.form-title {
				font-size: 36rpx;
				font-weight: bold;
				color: #333;
				text-align: center;
				margin-bottom: 20rpx;
			}

			.lounge-title {
				font-size: 28rpx;
				color: #666;
				text-align: center;
				margin-bottom: 40rpx;
				padding: 15rpx;
				background-color: #f8f8f8;
				border-radius: 10rpx;
			}

			.form-item {
				margin-bottom: 40rpx;

				.label {
					font-size: 28rpx;
					color: #333;
					margin-bottom: 15rpx;

					.required {
						color: #ff0000;
						margin-right: 5rpx;
					}
				}

				.input {
					width: 100%;
					height: 80rpx;
					border: 2rpx solid #e5e5e5;
					border-radius: 10rpx;
					padding: 0 20rpx;
					font-size: 28rpx;
					box-sizing: border-box;
				}

				.textarea {
					width: 100%;
					min-height: 200rpx;
					border: 2rpx solid #e5e5e5;
					border-radius: 10rpx;
					padding: 20rpx;
					font-size: 28rpx;
					box-sizing: border-box;
				}

				.char-count {
					text-align: right;
					font-size: 24rpx;
					color: #999;
					margin-top: 10rpx;
				}
			}

			.submit-btn {
				width: 100%;
				height: 90rpx;
				background: linear-gradient(135deg, #ff9a9e 0%, #fad0c4 100%);
				color: #fff;
				font-size: 32rpx;
				font-weight: bold;
				border-radius: 45rpx;
				border: none;
				margin-top: 40rpx;

				&:disabled {
					opacity: 0.6;
				}
			}
		}
	}
</style>