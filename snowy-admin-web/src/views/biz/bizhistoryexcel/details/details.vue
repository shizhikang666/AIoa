<template>
	<xn-form-container :title="formData.name" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<div class="table-container" id="rand-table-wrapper">
			<randerTable ref="randerTableRef" :data="allData"></randerTable>
		</div>

		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizHistoryExcelDetails">
	import { cloneDeep } from 'lodash-es'
	import randerTable from './randerTable/index.vue'
	import bizHistoryExcelApi from '@/api/biz/bizHistoryExcelApi'
	import { safeJsonParse } from '@/utils/json'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const randerTableRef = ref(null)

	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)

	const allData = ref([])

	// 打开抽屉
	const onOpen = async (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
		allData.value = safeJsonParse(record.extJson, [])
	}

	// 关闭抽屉
	const onClose = () => {
		formData.value = {}
		open.value = false
	}

	// 验证并提交数据
	const onSubmit = async () => {
		try {
			submitLoading.value = true
			const param = Object.assign({}, formData.value)
			const arrray = randerTableRef.value.getData()

			const data = []
			arrray.forEach((table) => {
				const rows = []

				Object.keys(table.rows).forEach((key) => {
					const row = table.rows[key]
					if (row.cells) {
						const cells = []
						Object.keys(row.cells).forEach((col_index) => {
							const cell = row.cells[col_index]
							cells.push({
								text: cell.text,
								merge: cell.merge
							})
						})
						rows.push(cells)
					}
				})

				data.push({
					table: rows,
					name: table.name
				})
			})

			param.extJson = JSON.stringify(data)

			await bizHistoryExcelApi.bizHistoryExcelSubmitForm(param, true)
			onClose()
			emit('successful')
		} catch (e) {
			console.error(e)
		} finally {
			submitLoading.value = false
		}
	}
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>

<style scoped lang="less">
	.table-container {
		overflow: hidden;
		position: relative;
		height: 100%;
	}
</style>
