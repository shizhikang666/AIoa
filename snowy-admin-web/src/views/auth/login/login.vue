<template>
	<div class="login_background" :class="store.theme === 'realDark' ? 'dark_bg' : ''">
		<div class="login_background_front"></div>
		<div class="login_main">
			<div class="login_config">
				<a-dropdown>
					<global-outlined />
					<template #overlay>
						<a-menu>
							<a-menu-item
								v-for="item in lang"
								:key="item.value"
								:command="item"
								:class="{ selected: config.lang === item.value }"
								@click="configLang(item.value)"
							>
								{{ item.name }}
							</a-menu-item>
						</a-menu>
					</template>
				</a-dropdown>
			</div>
			<div class="login-form">
				<a-card>
					<div class="login-header">
						<div class="logo">
							<img :alt="sysBaseConfig.SNOWY_SYS_NAME" :src="sysBaseConfig.SNOWY_SYS_LOGO" />
							<label>{{ params.name ? params.name : sysBaseConfig.SNOWY_SYS_NAME }}</label>
						</div>
						<!--<h2>{{ $t('login.signInTitle') }}</h2>-->
					</div>
					<a-tabs v-model:activeKey="activeKey">
						<a-tab-pane key="userAccount" :tab="$t('login.accountPassword')">
							<a-form ref="loginForm" :model="ruleForm" :rules="rules">
								<a-form-item name="tenantId">
									<a-input
										v-model:value="ruleForm.tenantId"
										:placeholder="$t('login.tenant')"
										size="large"
										@keyup.enter="login"
									>
										<template #prefix>
											<home-outlined class="login-icon-gray" />
										</template>
									</a-input>
								</a-form-item>
								<a-form-item name="account">
									<a-input
										v-model:value="ruleForm.account"
										:placeholder="$t('login.accountPlaceholder')"
										size="large"
										@keyup.enter="login"
									>
										<template #prefix>
											<UserOutlined class="login-icon-gray" />
										</template>
									</a-input>
								</a-form-item>

								<a-form-item name="password">
									<a-input-password
										v-model:value="ruleForm.password"
										:placeholder="$t('login.PWPlaceholder')"
										size="large"
										autocomplete="off"
										@keyup.enter="login"
									>
										<template #prefix>
											<LockOutlined class="login-icon-gray" />
										</template>
									</a-input-password>
								</a-form-item>
								<a-form-item name="validCode" v-if="captchaOpen === 'true'">
									<a-row :gutter="8">
										<a-col :span="17">
											<a-input
												v-model:value="ruleForm.validCode"
												:placeholder="$t('login.validLaceholder')"
												size="large"
												@keyup.enter="login"
											>
												<template #prefix>
													<verified-outlined class="login-icon-gray" />
												</template>
											</a-input>
										</a-col>
										<a-col :span="7">
											<img :src="validCodeBase64" class="login-validCode-img" @click="loginCaptcha" />
										</a-col>
									</a-row>
								</a-form-item>

								<a-form-item>
									<a href="/findpwd" class="xn-color-0d84ff">{{ $t('login.forgetPassword') }}？</a>
								</a-form-item>
								<a-form-item>
									<a-button type="primary" class="w-full" :loading="loading" round size="large" @click="login"
										>{{ $t('login.signIn') }}
									</a-button>
								</a-form-item>
							</a-form>
						</a-tab-pane>
						<!--暂时禁止手机号码登录-->
						<!-- <a-tab-pane key="userSms" :tab="$t('login.phoneSms')" force-render>
							<phone-login-form />
						</a-tab-pane> -->
					</a-tabs>
					<!--暂时隐藏其他号码登录-->
					<!-- <three-login /> -->
				</a-card>
			</div>
		</div>
	</div>
</template>
<script setup>
	import loginApi from '@/api/auth/loginApi'
	import PhoneLoginForm from './phoneLoginForm.vue'
	import ThreeLogin from './threeLogin.vue'
	import smCrypto from '@/utils/smCrypto'
	import { required } from '@/utils/formRules'
	import { afterLogin } from './util'
	import configData from '@/config'
	import configApi from '@/api/dev/configApi'
	import tool from '@/utils/tool'
	import { useUrlSearchParams } from '@vueuse/core'

	const params = useUrlSearchParams('history')
	const normalizeTenantId = (value) => {
		if (Array.isArray(value)) {
			value = value[0]
		}

		return value === undefined || value === null ? '' : String(value).trim()
	}
	const localTenantId = normalizeTenantId(localStorage.getItem('tenantId'))

	import { globalStore, iframeStore, keepAliveStore, viewTagsStore } from '@/store'

	const { proxy } = getCurrentInstance()
	const activeKey = ref('userAccount')
	const captchaOpen = ref(configData.SYS_BASE_CONFIG.SNOWY_SYS_DEFAULT_CAPTCHA_OPEN)
	const validCodeBase64 = ref('')
	const loading = ref(false)
	const tenantId = normalizeTenantId(params.tenantId || localTenantId)

	const ruleForm = reactive({
		account: '',
		password: '',
		tenantId,
		validCode: '',
		validCodeReqNo: '',
		autologin: false
	})

	const rules = reactive({
		account: [required(proxy.$t('login.accountError'), 'blur')],
		tenantId: [required(proxy.$t('login.tenantError'), 'blur')],
		password: [required(proxy.$t('login.PWError'), 'blur')]
	})
	const lang = ref([
		{
			name: '简体中文',
			value: 'zh-cn'
		},
		{
			name: 'English',
			value: 'en'
		}
	])
	const config = ref({
		lang: tool.data.get('APP_LANG') || proxy.$CONFIG.LANG,
		theme: tool.data.get('APP_THEME') || 'default'
	})

	const store = globalStore()
	const kStore = keepAliveStore()
	const iStore = iframeStore()
	const vStore = viewTagsStore()

	const setSysBaseConfig = store.setSysBaseConfig
	const clearKeepLive = kStore.clearKeepLive
	const clearIframeList = iStore.clearIframeList
	const clearViewTags = vStore.clearViewTags

	const sysBaseConfig = computed(() => {
		return store.sysBaseConfig
	})

	onMounted(() => {
		let formData = ref(configData.SYS_BASE_CONFIG)
		configApi.configSysBaseList().then((data) => {
			if (data) {
				data.forEach((item) => {
					formData.value[item.configKey] = item.configValue
				})
				captchaOpen.value = formData.value.SNOWY_SYS_DEFAULT_CAPTCHA_OPEN
				tool.data.set('SNOWY_SYS_BASE_CONFIG', formData.value)
				setSysBaseConfig(formData.value)
				refreshSwitch()
			}
		})
	})

	onBeforeMount(() => {
		clearViewTags()
		clearKeepLive()
		clearIframeList()
	})

	// 监听语言
	watch(
		() => config.value.lang,
		(newValue) => {
			proxy.$i18n.locale = newValue
			tool.data.set('APP_LANG', newValue)
		}
	)
	// 主题
	watch(
		() => config.value.theme,
		(newValue) => {
			document.body.setAttribute('data-theme', newValue)
		}
	)
	// 通过开关加载内容
	const refreshSwitch = () => {
		// 判断是否开启验证码
		if (captchaOpen.value === 'true') {
			// 加载验证码
			loginCaptcha()
			// 加入校验
			rules.validCode = [required(proxy.$t('login.validError'), 'blur')]
		}
	}

	// 获取验证码
	const loginCaptcha = () => {
		if (captchaOpen.value !== 'true') {
			validCodeBase64.value = ''
			ruleForm.validCode = ''
			ruleForm.validCodeReqNo = ''
			return
		}

		loginApi.getPicCaptcha().then((data) => {
			validCodeBase64.value = data.validCodeBase64
			ruleForm.validCodeReqNo = data.validCodeReqNo
		})
	}
	//登陆
	const loginForm = ref()
	const login = async () => {
		loginForm.value
			.validate()
			.then(async () => {
				loading.value = true
				const loginData = {
					account: ruleForm.account,
					// 密码进行SM2加密，传输过程中看到的只有密文，后端存储使用hash
					password: smCrypto.doSm2Encrypt(ruleForm.password),
					tenantId: ruleForm.tenantId
				}
				if (captchaOpen.value === 'true') {
					loginData.validCode = ruleForm.validCode
					loginData.validCodeReqNo = ruleForm.validCodeReqNo
				}
				const tenantId = ruleForm.tenantId
				// 获取token
				try {
					const loginToken = await loginApi.login(loginData)
					const loginAfter = await afterLogin(loginToken, tenantId)
					localStorage.setItem('tenantId', loginData.tenantId)
				} catch (err) {
					loading.value = false
					if (captchaOpen.value === 'true') {
						loginCaptcha()
					}
					console.error(err)
				}
			})
			.catch((error) => {
				console.error(error)
			})
	}
	const configLang = (key) => {
		config.value.lang = key
	}
</script>
<style lang="less">
	@import 'login';

	.xn-color-0d84ff {
		color: #0d84ff;
	}
</style>
