import { Modal } from 'ant-design-vue'
import { createVersionPolling } from 'version-polling'

/**
 *更新检测用于检测版本更新
 * @returns {Promise<unknown>}
 */
const showConfirm = () => {
	return new Promise((resolve) => {
		Modal.confirm({
			title: '更新提示',
			content: '页面有更新，点击确定刷新页面！',
			onOk() {
				resolve(true)
			},
			onCancel() {
				resolve(false)
			}
		})
	})
}
createVersionPolling({
	appETagKey: '__APP_ETAG__',
	pollingInterval: 5 * 1000, // 单位为毫秒
	silent: process.env.NODE_ENV === 'development', // 开发环境下不检测
	onUpdate: async (self) => {
		let flag = await showConfirm()
		if (flag) {
			self.onRefresh()
		} else {
			self.onCancel()
		}

		// 当检测到有新版本时，执行的回调函数，可以在这里提示用户刷新页面
	}
})
