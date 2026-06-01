// 导出 runWebWorker 函数
export const runWebWorker = (worker, data) => {
	return new Promise((resolve, reject) => {
		// 发送数据给 Web Worker
		worker.postMessage({ ...data })
		// 接收 Web Worker 的处理结果
		worker.onmessage = (event) => {
			resolve(event.data) // 处理完成，返回结果
			worker.terminate() // 释放 Web Worker 资源
		}
		// 捕获 Web Worker 的错误
		worker.onerror = (event) => {
			reject(new Error(`Web Worker 错误: ${event.message}`)) // 发生错误，拒绝 Promise
			worker.terminate() // 释放 Web Worker 资源
		}

		// 捕获 Web Worker 的异常
		worker.onmessageerror = (event) => {
			reject(new Error(`Web Worker 消息错误: ${event.message}`)) // 发生错误，拒绝 Promise
			worker.terminate() // 释放 Web Worker 资源
		}
	})
}
