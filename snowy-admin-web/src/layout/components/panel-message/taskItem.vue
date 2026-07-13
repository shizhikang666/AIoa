<template>
	<div style="width: 500px">
		<a-skeleton active :loading="loading">
			<a-list :pagination="true" size="small" :data-source="list">
				<template #renderItem="{ item }">
					<a-list-item>
						<a-typography-link @click="open(item)">{{ item.title }}</a-typography-link>
					</a-list-item>
				</template>
			</a-list>
		</a-skeleton>
		<processDetails @successful="loadData" ref="processDetailsRef"></processDetails>
	</div>
</template>

<script setup>
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import bizTaskApi from '@/api/biz/bizTaskApi'

	const loading = ref(false)
	const error = ref(false)
	const list = ref([])
	const processDetailsRef = ref()
	const loadData = async () => {
		loading.value = true
		error.value = false
		try {
			const res = await bizTaskApi.bizTaskList({})
			list.value = res.records
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}
	const open = (record) => {
		nextTick(() => {
			// 或者手动触发 blur 事件

			const event = new MouseEvent('mousedown', {
				bubbles: true, // 事件是否冒泡
				cancelable: true, // 事件是否可取消
				view: window // 事件的视图
			})

			// 手动触发点击事件
			document.body.dispatchEvent(event)
		})

		processDetailsRef.value.onOpen(record, record.id)
	}
	loadData()

	// 抛出函数
	defineExpose({
		loadData
	})
</script>

<style scoped></style>
