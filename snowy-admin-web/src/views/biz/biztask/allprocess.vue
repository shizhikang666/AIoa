<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="所属组织：" name="orgId">
						<a-tree-select
							v-model:value="searchFormState.orgId"
							class="xn-wd"
							:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
							placeholder="请选择组织"
							allow-clear
							:tree-data="treeData"
							:field-names="{
								children: 'children',
								label: 'name',
								value: 'id'
							}"
							selectable="false"
							tree-line
						></a-tree-select>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="标题搜索" name="title">
						<a-input placeholder="请输入标题" v-model:value="searchFormState.title"></a-input>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="金额/请假天数" name="amount">
						<a-input placeholder="金额/请假天数" v-model:value="searchFormState.amount"></a-input>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="审批类别" name="category">
						<a-select
							placeholder="请选择审批类型"
							v-model:value="searchFormState.category"
							:options="categoryOptions"
						></a-select>
						<!--						<a-input v-model:value="searchFormState.category" placeholder="请输入流程类别" />-->
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="完成时间" name="createTime">
						<a-range-picker v-model:value="searchFormState.createTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
				</a-col>
			</a-row>
		</a-form>
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
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
					{{ displayProcessCategory(record) }}
				</template>
				<template v-if="column.dataIndex === 'amount'">
					{{ record.variable.amount ? record.variable.amount : '--' }} {{ calcUnit(record.category) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-button :disabled="record.endTime != null" type="link" @click="cancelProcess(record)" danger>
							取消申请
						</a-button>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<TaskDetails @successful="tableRef.refresh()" ref="taskDetails"></TaskDetails>
</template>

<script setup name="allprocess">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import bizProcessPage from '@/api/biz/bizProcessApi'
	import TaskDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { App } from 'ant-design-vue'
	import { ref } from 'vue'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const categoryOptions = ref([])
	categoryOptions.value = tool.dictListByPath(['APPROVAL_PROCESS', 'progress_category'])
	const displayProcessCategory = (record) => {
		return (
			record.categoryName ||
			record.processCategoryName ||
			tool.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_category', record.category)
		)
	}

	console.log(categoryOptions)

	const { modal } = App.useApp()
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
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
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '数量（天数/金额）',
			dataIndex: 'amount'
		},
		{
			title: '流程状态',
			dataIndex: 'status'
		},
		{
			title: '流程类别',
			dataIndex: 'category'
		},
		{
			title: '创建时间',
			dataIndex: 'createTime'
		},
		{
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: 150
		}
	]

	const selectedRowKeys = ref([])
	// 列表选择配置
	const options = {
		// columns数字类型字段加入 needTotal: true 可以勾选自动算账
		alert: {
			show: true,
			clear: () => {
				selectedRowKeys.value = ref([])
			}
		},
		rowSelection: {
			onChange: (selectedRowKey, selectedRows) => {
				selectedRowKeys.value = selectedRowKey
			}
		}
	}
	const loadData = (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		return bizProcessPage.bizProcessAllPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
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

	const calcUnit = (category) => {
		switch (category) {
			case 'Process_sale_project_product_return':
				return '（¥）'
			case 'Process_make_payment':
				return '（¥）'
			case 'Process_payment':
				return '（¥）'
			case 'Process_reimbursement':
				return '（¥）'
			case 'Process_sale_project_play':
				return '（¥）'
			case 'Process_ask_leave':
				return '天'
			default:
				return ''
		}
	}
</script>
