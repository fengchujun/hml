# App.vue 修改说明

## 需要修改的文件
`/home/user/hml/uniapp/App.vue`

## 修改位置
在 `getMemberInfo()` 方法处（约第333-344行）

## 原代码
```javascript
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
```

## 修改后代码
```javascript
getMemberInfo(callback) {
	this.$api.sendRequest({
		url: '/api/member/info',
		success: (res) => {
			if (res.code >= 0) {
				// 登录成功，存储会员信息
				this.$store.commit('setMemberInfo', res.data);
				this.$store.dispatch('init');

				// 检查是否需要引导申请特邀会员
				this.checkVipInvitation(res.data);

				if (callback) callback();
			}
		}
	});
},
/**
 * 检查特邀会员邀请（登录/注册后检查）
 */
checkVipInvitation(memberInfo) {
	// 获取推荐人ID
	let source_member = uni.getStorageSync('source_member');

	// 如果没有推荐人，或者已经是特邀会员，不需要处理
	if (!source_member || memberInfo.member_level == 2) {
		return;
	}

	// 检查推荐人是否是特邀会员并有名额
	this.$api.sendRequest({
		url: '/api/membervip/checkInviterQuota',
		data: {
			inviter_id: source_member
		},
		success: (res) => {
			if (res.code >= 0 && res.data.has_quota) {
				// 推荐人是特邀会员且有名额，跳转到申请页面
				setTimeout(() => {
					this.$util.redirectTo('/pages_tool/member/vip_apply?inviter_id=' + source_member);
				}, 500);
			}
			// 如果没有名额或不是特邀会员，用户正常成为普通会员，无需额外处理
		}
	});
}
```

## 修改说明
1. **getMemberInfo()** 方法添加 `callback` 参数，支持回调
2. **新增 checkVipInvitation()** 方法：
   - 检查是否有推荐人（source_member）
   - 如果用户已经是特邀会员，不处理
   - 调用API检查推荐人是否是特邀会员且有名额
   - 如果满足条件，跳转到特邀会员申请页面

## 业务逻辑
- 用户通过特邀会员邀请链接进入小程序
- 注册/登录完成后
- 系统自动检查推荐人是否是特邀会员
- 如果是且有名额，自动引导用户填写申请表
- 如果不是或没有名额，用户正常成为普通会员

## 注意事项
- 这个修改确保了特邀会员邀请流程的完整性
- 跳转有500ms延迟，确保登录流程完整
- 不影响普通会员的注册流程
