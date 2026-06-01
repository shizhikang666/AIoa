<template>
	<a-card :bordered="false">
		<a-table ref="tableRef" :columns="columns" :dataSource="data" bordered :row-key="(record) => record.id">
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'title'">
					<a-typography-link @click="openForm(record)">
						{{ record.title }}
					</a-typography-link>
				</template>

				<template v-if="column.dataIndex === 'status'">
					<a-tag :color="$TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state_color', record.status)">
						{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state', record.status) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_category', record.category) }}
				</template>
				<!--				<template v-if="column.dataIndex === 'action'">-->
				<!--					<a-space>-->
				<!--						<a-button :disabled="record.endTime != null" type="link" @click="cancelProcess(record)" danger>-->
				<!--							取消申请-->
				<!--						</a-button>-->
				<!--					</a-space>-->
				<!--				</template>-->
			</template>
		</a-table>
	</a-card>
	<TaskDetails @successful="tableRef.refresh()" ref="taskDetails"></TaskDetails>
</template>

<script setup name="purchaseOrderProcess">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import TaskDetails from '@/views/biz/bizprocess/processDetails/index.vue'

	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { App } from 'ant-design-vue'
	import { ref } from 'vue'

	// eslint-disable-next-line vue/no-setup-props-destructure
	let { id } = defineProps({
		id: {
			type: String,
			required: true
		}
	})

	const runtimeCount = defineModel('runtimeCount')

	const categoryOptions = ref([])
	categoryOptions.value = tool.dictListByPath(['APPROVAL_PROCESS', 'progress_category'])
	const { modal } = App.useApp()
	const searchFormState = ref({})
	const searchFormRef = ref()

	const taskDetails = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '标题',
			dataIndex: 'title'
		},
		{
			title: '流程状态',
			dataIndex: 'status'
		}
		// {
		// 	title: '流程类别',
		// 	dataIndex: 'category'
		// },
		// {
		// 	title: '创建时间',
		// 	dataIndex: 'createTime'
		// },
		// {
		// 	title: '操作',
		// 	dataIndex: 'action',
		// 	align: 'center',
		// 	width: 150
		// }
	]

	const data = ref([])

	const loadData = async () => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		data.value = await bizProcessApi.bizProcessQueryList(
			Object.assign(searchFormParam, {
				processKeyList: ['Process_procure_in_warehouse', 'Process_reimbursement', 'Process_make_payment'],
				attribute: {
					objectId: id
				}
			})
		)

		runtimeCount.value = data.value.filter((r) => r.status === 'progress').length
	}

	loadData()
	// 重置
	const reset = async () => {
		searchFormRef.value.resetFields()
		await loadData()
	}

	const openForm = (record) => {
		taskDetails.value.onOpen(record)
	}

	const cancelProcess = (record) => {
		modal.confirm({
			title: '取消',
			content: '确认要取消吗',
			okType: 'danger',

			onOk() {
				bizProcessApi
					.bizProcessCancel({ id: record.id })
					.then(() => {
						tableRef.value.refresh()
					})
					.catch((v) => {
						tableRef.value.refresh()
					})
			},
			onCancel() {}
		})
	}
</script>
