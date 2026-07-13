import DEFAULT_CONFIG from '@/config/index'
import { baseRequest } from '@/utils/request'
import loginApi from '@/api/auth/loginApi'

function askPermission() {
	return new Promise(function (resolve, reject) {
		const permissionResult = Notification.requestPermission(function (result) {
			resolve(result)
		})
		if (permissionResult) {
			permissionResult.then(resolve, reject)
		}
	}).then(function (permissionResult) {
		if (permissionResult !== 'granted') {
			throw new Error("We weren't granted permission.")
		}
		console.log('权限检测成功！')
	})
}

function registerServiceWorker() {
	return navigator.serviceWorker
		.register('/service-worker.js', { scope: '/' })
		.then(function (registration) {
			console.log('Service worker registered.')
			return registration.pushManager.subscribe({
				userVisibleOnly: true,
				applicationServerKey: urlBase64ToUint8Array(DEFAULT_CONFIG.PUBLIC_KEY)
			})
		})
		.then((subscription) => {
			// 将 subscription 发送到服务器
			console.log('registerServiceWorker')
			return loginApi.doSubscription(subscription)
		})
		.catch(function (err) {
			console.error('Unable to register service worker.', err)
		})
}

async function subscribeUser() {
	const registration = await navigator.serviceWorker.ready
	return registration.pushManager
		.subscribe({
			userVisibleOnly: true,
			applicationServerKey: urlBase64ToUint8Array(DEFAULT_CONFIG.PUBLIC_KEY)
		})
		.then((subscription) => {
			console.log('subscribeUser')
			return loginApi.doSubscription(subscription)
		})
		.catch(function (err) {
			console.error('Unable to register service worker.', err)
		})
}

async function createServiceWorker() {
	if (!('serviceWorker' in navigator)) {
		// 此浏览器不支持 Service Worker，禁用或隐藏 UI
		return
	}

	if (!('PushManager' in window)) {
		// 此浏览器不支持推送，禁用或隐藏 UI
		return
	}
	try {
		await askPermission()
		await registerServiceWorker()
	} catch (err) {
		console.error('订阅失败', err)
	}
}

function urlBase64ToUint8Array(base64String) {
	const padding = '='.repeat((4 - (base64String.length % 4)) % 4)
	const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/')

	const rawData = window.atob(base64)
	const outputArray = new Uint8Array(rawData.length)

	for (let i = 0; i < rawData.length; ++i) {
		outputArray[i] = rawData.charCodeAt(i)
	}
	return outputArray
}

//续签
async function checkSubscription() {
	const registration = await navigator.serviceWorker.ready
	const subscription = await registration.pushManager.getSubscription()
	if (subscription) {
		return subscription
	} else {
		await subscribeUser()
	}
}

export { createServiceWorker, checkSubscription }
