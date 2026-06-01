export function useLoading(fun) {
	const loading = ref(false)
	const error = ref(false)

	const load = async (...arg) => {
		try {
			loading.value = true // 开始加载
			await fun(...arg) // 执行异步操作
			error.value = false // 成功时重置错误状态
		} catch (e) {
			console.error(e) // 捕获并记录错误
			error.value = true // 设置错误状态
		} finally {
			loading.value = false // 无论成功或失败，都重置加载状态
		}
	}

	return {
		load,
		error,
		loading
	}
}
