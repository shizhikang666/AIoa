<template>
	<div class="user-bar">
		<!-- 搜索面板 -->
		<panel-search v-if="!isMobile" />

		<div v-if="!isMobile" class="screen panel-item hidden-sm-and-down" @click="fullscreen">
			<fullscreen-outlined />
		</div>
		<panel-message v-if="!isMobile"></panel-message>
		<div v-if="!isMobile" class="panel-item">
			<a-config-provider
				:theme="{
					components: {
						Switch: {
							colorPrimary: '#1c4061'
						}
					}
				}"
			>
				<a-switch @change="changeSideStyle" :checked-value="store.theme !== themeEnum.REAL_DARK">
					<template #unCheckedChildren>
						<svg
							width="1em"
							height="1em"
							fill="currentColor"
							viewBox="0 0 1024 1024"
							t="1730682111814"
							version="1.1"
							xmlns="http://www.w3.org/2000/svg"
							p-id="4261"
						>
							<path
								d="M516.475649 963.330096a449.554045 449.554045 0 1 1 119.634799-882.910504l61.238241 16.765922-37.22603 51.576523a237.138338 237.138338 0 0 0-45.040655 139.810739 239.553767 239.553767 0 0 0 239.269599 239.269599A235.007076 235.007076 0 0 0 909.338143 521.448592l61.664493-14.208409-7.814624 62.801166A450.122381 450.122381 0 0 1 516.475649 963.330096z m0-810.873873a361.319828 361.319828 0 1 0 346.543083 463.478285 327.6459 327.6459 0 0 1-307.754128-461.347024 355.210212 355.210212 0 0 0-38.788955-2.131261z"
								p-id="4262"
							></path>
						</svg>
					</template>
					<template #checkedChildren>
						<svg
							t="1730682246319"
							class="icon"
							version="1.1"
							xmlns="http://www.w3.org/2000/svg"
							p-id="5392"
							width="1em"
							height="1em"
							fill="currentColor"
							viewBox="0 0 1024 1024"
						>
							<path
								d="M519.539208 791.717679c-155.639756 0-282.253432-126.608559-282.253432-282.233989 0-155.627477 126.613676-282.240129 282.253432-282.240129 155.639756 0 282.253432 126.613676 282.253432 282.240129C801.792641 665.10912 675.178965 791.717679 519.539208 791.717679zM519.539208 280.58135c-126.222773 0-228.915643 102.679567-228.915643 228.90234s102.69287 228.895177 228.915643 228.895177c126.223796 0 228.915643-102.672404 228.915643-228.895177S645.763005 280.58135 519.539208 280.58135z"
								p-id="5393"
							></path>
							<path
								d="M373.274984 424.943248c-2.317789 0-4.662183-0.598634-6.797823-1.861394-6.329149-3.76372-8.4126-11.94096-4.662183-18.283412 27.606755-46.515407 78.341253-75.410504 132.395356-75.410504 7.369851 0 13.33368 5.963829 13.33368 13.334703s-5.963829 13.334703-13.33368 13.334703c-44.691875 0-86.636173 23.896246-109.450784 62.362326C382.260635 422.61318 377.832789 424.943248 373.274984 424.943248z"
								p-id="5394"
							></path>
							<path
								d="M353.651041 491.721141c-7.370875 0-13.334703-5.963829-13.334703-13.334703 0-12.43624 1.458211-19.259646 4.050246-30.263258 1.680269-7.175423 8.972349-11.576663 16.031115-9.93528 7.175423 1.692548 11.615549 8.867972 9.93528 16.030092-2.317789 9.870812-3.347235 14.624069-3.347235 24.169469C366.985744 485.757313 361.021915 491.721141 353.651041 491.721141z"
								p-id="5395"
							></path>
							<path
								d="M530.268574 958.712041c-14.728446 0-26.669406-11.954263-26.669406-26.669406l0-80.008218c0-14.740726 11.94096-26.669406 26.669406-26.669406s26.669406 11.927657 26.669406 26.669406l0 80.008218C556.939003 946.758802 544.99702 958.712041 530.268574 958.712041z"
								p-id="5396"
							></path>
							<path
								d="M530.268574 198.632943c-14.728446 0-26.669406-11.94096-26.669406-26.669406L503.599168 91.956342c0-14.728446 11.94096-26.669406 26.669406-26.669406s26.669406 11.94096 26.669406 26.669406l0 80.007195C556.939003 186.691983 544.99702 198.632943 530.268574 198.632943z"
								p-id="5397"
							></path>
							<path
								d="M221.658867 833.153394c-6.823406 0-13.646811-2.604314-18.856463-7.812943-10.417257-10.41828-10.417257-27.294646 0-37.712927l56.568367-56.567343c10.417257-10.41828 27.294646-10.41828 37.711903 0 10.417257 10.417257 10.417257 27.293623 0 37.712927l-56.568367 56.567343C235.306702 830.54908 228.482273 833.153394 221.658867 833.153394z"
								p-id="5398"
							></path>
							<path
								d="M759.105423 295.712979c-6.823406 0-13.646811-2.604314-18.85544-7.812943-10.41828-10.41828-10.41828-27.294646 0-37.711903l56.567343-56.568367c10.41828-10.417257 27.294646-10.417257 37.712927 0 10.417257 10.41828 10.417257 27.294646 0 37.711903l-56.567343 56.568367C772.753257 293.108664 765.929852 295.712979 759.105423 295.712979z"
								p-id="5399"
							></path>
							<path
								d="M278.227234 295.712979c-6.823406 0-13.646811-2.604314-18.85544-7.812943l-56.568367-56.568367c-10.417257-10.417257-10.417257-27.293623 0-37.711903 10.41828-10.417257 27.294646-10.417257 37.711903 0l56.568367 56.568367c10.417257 10.417257 10.417257 27.293623 0 37.711903C291.874045 293.108664 285.05064 295.712979 278.227234 295.712979z"
								p-id="5400"
							></path>
							<path
								d="M815.674812 833.178977c-6.824429 0-13.647835-2.604314-18.856463-7.812943l-56.567343-56.593949c-10.41828-10.419304-10.41828-27.29567 0-37.712927 10.417257-10.41828 27.293623-10.41828 37.712927 0l56.567343 56.593949c10.417257 10.41828 10.417257 27.294646 0 37.712927C829.322647 830.574662 822.498218 833.178977 815.674812 833.178977z"
								p-id="5401"
							></path>
							<path
								d="M171.967118 545.332153l-80.008218 0c-14.728446 0-26.669406-11.94096-26.669406-26.669406s11.94096-26.669406 26.669406-26.669406l80.008218 0c14.728446 0 26.669406 11.94096 26.669406 26.669406S186.694541 545.332153 171.967118 545.332153z"
								p-id="5402"
							></path>
							<path
								d="M932.039054 545.332153l-80.008218 0c-14.728446 0-26.669406-11.94096-26.669406-26.669406s11.94096-26.669406 26.669406-26.669406l80.008218 0c14.728446 0 26.669406 11.94096 26.669406 26.669406S946.7675 545.332153 932.039054 545.332153z"
								p-id="5403"
							></path>
						</svg>
					</template>
				</a-switch>
			</a-config-provider>
		</div>

		<!--	<dev-user-message />-->
		<a-dropdown class="user panel-item">
			<div class="user-avatar">
				<a-avatar :src="userInfo ? userInfo.avatar : undefined" />
				<label>{{ userName }}</label>
			</div>
			<template #overlay>
				<a-menu>
					<a-menu-item key="uc" @click="handleUser('uc')">
						<UserOutlined class="xn-mr8" />
						<span>个人中心</span>
					</a-menu-item>
					<a-menu-item key="clearCache" @click="handleUser('clearCache')">
						<loading3-quarters-outlined class="xn-mr8" />
						<span>清理缓存</span>
					</a-menu-item>
					<a-menu-divider />
					<a-menu-item key="outLogin" @click="handleUser('outLogin')">
						<export-outlined class="xn-mr8" />
						<span>退出登录</span>
					</a-menu-item>
				</a-menu>
			</template>
		</a-dropdown>

		<!--		<a-dropdown v-if="!isMobile" class="panel-item">-->
		<!--			<global-outlined />-->
		<!--			<template #overlay>-->
		<!--				<a-menu :selected-keys="lang">-->
		<!--					<a-menu-item key="zh-cn" @click="handleIn18('zh-cn')">-->
		<!--						<span>简体中文</span>-->
		<!--					</a-menu-item>-->
		<!--					<a-menu-item key="en" @click="handleIn18('en')">-->
		<!--						<span>English</span>-->
		<!--					</a-menu-item>-->
		<!--				</a-menu>-->
		<!--			</template>-->
		<!--		</a-dropdown>-->

		<!--		<a-segmented @change="setSideStyle" :value="store.theme" :options="sideStyleList">-->
		<!--			<template #label="item">-->
		<!--				<div style="padding: 4px 4px">-->
		<!--					<template v-if="item.value === themeEnum.REAL_DARK">-->
		<!--						<svg-->
		<!--							width="1em"-->
		<!--							height="1em"-->
		<!--							fill="currentColor"-->
		<!--							viewBox="0 0 1024 1024"-->
		<!--							t="1730682111814"-->
		<!--							version="1.1"-->
		<!--							xmlns="http://www.w3.org/2000/svg"-->
		<!--							p-id="4261"-->
		<!--						>-->
		<!--							<path-->
		<!--								d="M516.475649 963.330096a449.554045 449.554045 0 1 1 119.634799-882.910504l61.238241 16.765922-37.22603 51.576523a237.138338 237.138338 0 0 0-45.040655 139.810739 239.553767 239.553767 0 0 0 239.269599 239.269599A235.007076 235.007076 0 0 0 909.338143 521.448592l61.664493-14.208409-7.814624 62.801166A450.122381 450.122381 0 0 1 516.475649 963.330096z m0-810.873873a361.319828 361.319828 0 1 0 346.543083 463.478285 327.6459 327.6459 0 0 1-307.754128-461.347024 355.210212 355.210212 0 0 0-38.788955-2.131261z"-->
		<!--								p-id="4262"-->
		<!--							></path>-->
		<!--						</svg>-->
		<!--					</template>-->
		<!--					<template v-else-if="item.value === themeEnum.LIGHT">-->
		<!--						<svg-->
		<!--							t="1730682246319"-->
		<!--							class="icon"-->
		<!--							version="1.1"-->
		<!--							xmlns="http://www.w3.org/2000/svg"-->
		<!--							p-id="5392"-->
		<!--							width="1em"-->
		<!--							height="1em"-->
		<!--							fill="currentColor"-->
		<!--							viewBox="0 0 1024 1024"-->
		<!--						>-->
		<!--							<path-->
		<!--								d="M519.539208 791.717679c-155.639756 0-282.253432-126.608559-282.253432-282.233989 0-155.627477 126.613676-282.240129 282.253432-282.240129 155.639756 0 282.253432 126.613676 282.253432 282.240129C801.792641 665.10912 675.178965 791.717679 519.539208 791.717679zM519.539208 280.58135c-126.222773 0-228.915643 102.679567-228.915643 228.90234s102.69287 228.895177 228.915643 228.895177c126.223796 0 228.915643-102.672404 228.915643-228.895177S645.763005 280.58135 519.539208 280.58135z"-->
		<!--								p-id="5393"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M373.274984 424.943248c-2.317789 0-4.662183-0.598634-6.797823-1.861394-6.329149-3.76372-8.4126-11.94096-4.662183-18.283412 27.606755-46.515407 78.341253-75.410504 132.395356-75.410504 7.369851 0 13.33368 5.963829 13.33368 13.334703s-5.963829 13.334703-13.33368 13.334703c-44.691875 0-86.636173 23.896246-109.450784 62.362326C382.260635 422.61318 377.832789 424.943248 373.274984 424.943248z"-->
		<!--								p-id="5394"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M353.651041 491.721141c-7.370875 0-13.334703-5.963829-13.334703-13.334703 0-12.43624 1.458211-19.259646 4.050246-30.263258 1.680269-7.175423 8.972349-11.576663 16.031115-9.93528 7.175423 1.692548 11.615549 8.867972 9.93528 16.030092-2.317789 9.870812-3.347235 14.624069-3.347235 24.169469C366.985744 485.757313 361.021915 491.721141 353.651041 491.721141z"-->
		<!--								p-id="5395"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M530.268574 958.712041c-14.728446 0-26.669406-11.954263-26.669406-26.669406l0-80.008218c0-14.740726 11.94096-26.669406 26.669406-26.669406s26.669406 11.927657 26.669406 26.669406l0 80.008218C556.939003 946.758802 544.99702 958.712041 530.268574 958.712041z"-->
		<!--								p-id="5396"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M530.268574 198.632943c-14.728446 0-26.669406-11.94096-26.669406-26.669406L503.599168 91.956342c0-14.728446 11.94096-26.669406 26.669406-26.669406s26.669406 11.94096 26.669406 26.669406l0 80.007195C556.939003 186.691983 544.99702 198.632943 530.268574 198.632943z"-->
		<!--								p-id="5397"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M221.658867 833.153394c-6.823406 0-13.646811-2.604314-18.856463-7.812943-10.417257-10.41828-10.417257-27.294646 0-37.712927l56.568367-56.567343c10.417257-10.41828 27.294646-10.41828 37.711903 0 10.417257 10.417257 10.417257 27.293623 0 37.712927l-56.568367 56.567343C235.306702 830.54908 228.482273 833.153394 221.658867 833.153394z"-->
		<!--								p-id="5398"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M759.105423 295.712979c-6.823406 0-13.646811-2.604314-18.85544-7.812943-10.41828-10.41828-10.41828-27.294646 0-37.711903l56.567343-56.568367c10.41828-10.417257 27.294646-10.417257 37.712927 0 10.417257 10.41828 10.417257 27.294646 0 37.711903l-56.567343 56.568367C772.753257 293.108664 765.929852 295.712979 759.105423 295.712979z"-->
		<!--								p-id="5399"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M278.227234 295.712979c-6.823406 0-13.646811-2.604314-18.85544-7.812943l-56.568367-56.568367c-10.417257-10.417257-10.417257-27.293623 0-37.711903 10.41828-10.417257 27.294646-10.417257 37.711903 0l56.568367 56.568367c10.417257 10.417257 10.417257 27.293623 0 37.711903C291.874045 293.108664 285.05064 295.712979 278.227234 295.712979z"-->
		<!--								p-id="5400"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M815.674812 833.178977c-6.824429 0-13.647835-2.604314-18.856463-7.812943l-56.567343-56.593949c-10.41828-10.419304-10.41828-27.29567 0-37.712927 10.417257-10.41828 27.293623-10.41828 37.712927 0l56.567343 56.593949c10.417257 10.41828 10.417257 27.294646 0 37.712927C829.322647 830.574662 822.498218 833.178977 815.674812 833.178977z"-->
		<!--								p-id="5401"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M171.967118 545.332153l-80.008218 0c-14.728446 0-26.669406-11.94096-26.669406-26.669406s11.94096-26.669406 26.669406-26.669406l80.008218 0c14.728446 0 26.669406 11.94096 26.669406 26.669406S186.694541 545.332153 171.967118 545.332153z"-->
		<!--								p-id="5402"-->
		<!--							></path>-->
		<!--							<path-->
		<!--								d="M932.039054 545.332153l-80.008218 0c-14.728446 0-26.669406-11.94096-26.669406-26.669406s11.94096-26.669406 26.669406-26.669406l80.008218 0c14.728446 0 26.669406 11.94096 26.669406 26.669406S946.7675 545.332153 932.039054 545.332153z"-->
		<!--								p-id="5403"-->
		<!--							></path>-->
		<!--						</svg>-->
		<!--					</template>-->
		<!--					<template v-else-if="item.value === themeEnum.DARK">-->
		<!--						<svg-->
		<!--							t="1730682346025"-->
		<!--							class="icon"-->
		<!--							viewBox="0 0 1024 1024"-->
		<!--							version="1.1"-->
		<!--							xmlns="http://www.w3.org/2000/svg"-->
		<!--							p-id="6437"-->
		<!--							width="1em"-->
		<!--							height="1em"-->
		<!--							fill="currentColor"-->
		<!--						>-->
		<!--							<path-->
		<!--								d="M512 64a448 448 0 1 1 0 896A448 448 0 0 1 512 64z m-0.384 122.624c-12.928 0-25.728 0.64-38.336 2.176a573.12 573.12 0 0 1 0 645.568 323.072 323.072 0 0 0 268.16-92.992 323.2 323.2 0 0 0 95.168-229.76 323.072 323.072 0 0 0-95.232-229.76 323.2 323.2 0 0 0-229.76-95.232z"-->
		<!--								p-id="6438"-->
		<!--							></path>-->
		<!--						</svg>-->
		<!--					</template>-->
		<!--				</div>-->
		<!--			</template>-->
		<!--		</a-segmented>-->

		<div v-if="setDrawer === 'true'" class="setting panel-item" @click="openSetting">
			<layout-outlined />
		</div>
	</div>

	<!-- 整体风格设置抽屉 -->
	<a-drawer v-model:open="settingDialog" :closable="false" width="300">
		<setting />
	</a-drawer>
</template>

<script setup name="layoutUserBar">
	import { createVNode } from 'vue'
	import { ExclamationCircleOutlined } from '@ant-design/icons-vue'
	import { Modal } from 'ant-design-vue'
	import screenFull from 'screenfull'
	import { message } from 'ant-design-vue'
	import Setting from './setting.vue'
	import router from '@/router'
	import tool from '@/utils/tool'
	import config from '@/config/index'
	import loginApi from '@/api/auth/loginApi'
	import DevUserMessage from './message.vue'
	import PanelSearch from './panel-search/index.vue'
	import { globalStore } from '@/store'
	import { useI18n } from 'vue-i18n'

	const { locale } = useI18n()
	import PanelMessage from './panel-message/index.vue'
	import { themeEnum } from '@/layout/enum/themeEnum'
	import { layoutEnum } from '@/layout/enum/layoutEnum'

	const lang = ref(new Array(tool.data.get('APP_LANG') || config.LANG))
	import { checkSubscription } from '@/common/webpush'

	const settingDialog = ref(false)
	const setDrawer = ref(import.meta.env.VITE_SET_DRAWER)
	const store = globalStore()

	const isMobile = computed(() => {
		return store.isMobile
	})
	const userInfo = computed(() => {
		return store.userInfo
	})
	const userName = ref(userInfo.value?.userName || '')

	checkSubscription()
		.then((response) => {
			console.log('续签成功！')
		})
		.catch((error) => {
			console.log('续签失败', error)
		})

	// 个人信息
	const handleUser = (key) => {
		if (key === 'uc') {
			router.push({ path: '/usercenter' })
		}
		if (key === 'clearCache') {
			Modal.confirm({
				title: '提示',
				content: '确认清理所有缓存？',
				icon: createVNode(ExclamationCircleOutlined),
				maskClosable: false,
				okText: '确定',
				cancelText: '取消',
				onOk() {
					message.loading('正在清理中...', 1)
					tool.data.clear()
					setTimeout(() => {
						router.replace({ path: '/login' })
						location.reload()
					}, 100)
				},
				onCancel() {}
			})
		}
		if (key === 'outLogin') {
			Modal.confirm({
				title: '提示',
				content: '确认退出当前用户？',
				icon: createVNode(ExclamationCircleOutlined),
				maskClosable: false,
				onOk() {
					// 取得缓存中的token
					const token = tool.data.get('TOKEN')
					const param = {
						token: token
					}

					message.loading('退出中...', 1)
					loginApi
						.logout(param)
						.then(() => {
							// 清理掉个人的一些信息
							tool.data.remove('TOKEN')
							tool.data.remove('USER_INFO')
							tool.data.remove('MENU')
							tool.data.remove('PERMISSIONS')
							router.replace({ path: '/login' })
							nextTick(() => {
								// 清理缓存内的个人信息
								store.userInfo = undefined
							})
						})
						.catch(() => {
							tool.data.clear()
							router.replace({ path: '/login' })
							location.reload()
						})
				},
				onCancel() {}
			})
		}
	}
	// 设置多语言语种
	const handleIn18 = (key) => {
		lang.value = []
		lang.value.push(key)
		locale.value = key
		tool.data.set('APP_LANG', key)
	}

	const sideStyleList = ref([
		{
			tips: '暗色主题风格',
			value: themeEnum.DARK,
			style: 'snowy-setting-checkbox-item-dark'
		},
		{
			tips: '亮色主题风格',
			value: themeEnum.LIGHT,
			style: 'snowy-setting-checkbox-item-light'
		},
		{
			tips: '暗黑模式',
			value: themeEnum.REAL_DARK,
			style: 'snowy-setting-checkbox-item-realdark'
		}
	])
	const layout = computed(() => {
		return store.layout
	})
	const theme = computed(() => {
		return store.theme
	})
	const topHeaderThemeColorOpen = computed(() => {
		return store.topHeaderThemeColorOpen
	})
	const topHeaderThemeColorSpreadDisabled = ref(false)
	const topHeaderThemeColorOpenDisabled = ref(false)
	const moduleUnfoldDisabled = ref(false)
	const menuIsCollapseDisabled = ref(false)
	const sideUniqueOpenDisabled = ref(false)

	const setSideStyle = (value) => {
		store.setTheme(value)
		tool.data.set('SNOWY_THEME', value)
		layoutChange(layout.value)
	}
	const changeSideStyle = (value) => {
		setSideStyle(value ? themeEnum.REAL_DARK : themeEnum.LIGHT)
	}
	const layoutChange = (layout) => {
		// 暗黑色情况下，将其禁用顶栏颜色跟通栏功能
		if (theme.value === themeEnum.REAL_DARK) {
			topHeaderThemeColorOpenDisabled.value = true
			topHeaderThemeColorSpreadDisabled.value = true
			// 在切换主题风格的时候判断顶栏相关的，暗黑模式下是不允许开启顶栏变色的
			if (topHeaderThemeColorOpen.value) {
				toggleState('topHeaderThemeColorOpen')
				tool.data.set('SNOWY_TOP_HEADER_THEME_COLOR_SPREAD', false)
			}
		} else {
			if (layout !== layoutEnum.TOP) {
				topHeaderThemeColorSpreadDisabled.value = false
				topHeaderThemeColorOpenDisabled.value = false
			} else {
				topHeaderThemeColorOpenDisabled.value = false
			}
		}
		// 如果是顶栏布局，限制一些配置
		if (layout === layoutEnum.TOP) {
			// 将其模块坞功能禁用
			moduleUnfoldDisabled.value = true
			// 禁用折叠菜单
			menuIsCollapseDisabled.value = true
			// 菜单排他展开
			if (sideUniqueOpen.value) {
				toggleState('sideUniqueOpen')
			}
			sideUniqueOpenDisabled.value = true
			topHeaderThemeColorSpreadDisabled.value = true
		} else {
			moduleUnfoldDisabled.value = false
			menuIsCollapseDisabled.value = false
			sideUniqueOpenDisabled.value = false
		}
	}
	const toolDataNameMap = {
		menuIsCollapse: 'MENU_COLLAPSE',
		sideUniqueOpen: 'SIDE_UNIQUE_OPEN',
		layoutTagsOpen: 'LAYOUT_TAGS_OPEN',
		breadcrumbOpen: 'BREADCRUMD_OPEN',
		fixedWidth: 'FIXEDWIDTH_OPEN',
		topHeaderThemeColorOpen: 'TOP_HEADER_THEME_COLOR_OPEN',
		topHeaderThemeColorSpread: 'TOP_HEADER_THEME_COLOR_SPREAD',
		loginUserWatermarkOpen: 'LOGIN_USER_WATERMARK_OPEN',
		footerCopyrightOpen: 'FOOTER_COPYRIGHT_OPEN',
		roundedCornerStyleOpen: 'ROUNDED_CORNER_STYLE_OPEN',
		moduleUnfoldOpen: 'MODULE_UNFOLD_OPEN'
	}
	const toggleState = (stateName) => {
		store.toggleConfig(stateName)
		const toolDataName = toolDataNameMap[stateName]
		tool.data.set(`SNOWY_${toolDataName}`, store[stateName])
	}

	// 设置抽屉
	const openSetting = () => {
		settingDialog.value = true
	}
	// 全屏
	const fullscreen = () => {
		const element = document.documentElement
		if (screenFull.isEnabled) {
			screenFull.toggle(element)
		}
	}
</script>

<style lang="less" scoped>
	:deep(.ant-modal) {
		top: 20px;
	}

	:deep(.ant-modal-content) {
		border-radius: 10px;
	}

	.user-bar {
		display: flex;
		align-items: center;
		height: 100%;
	}

	.user-bar .user-avatar {
		height: 49px;
		display: flex;
		align-items: center;
	}

	.user-bar .user-avatar label {
		display: inline-block;
		margin-left: 5px;
		cursor: pointer;
	}

	.setting {
		margin-right: 10px;
	}
</style>
