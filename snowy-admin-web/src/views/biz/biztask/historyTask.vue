<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
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
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="完成时间" name="createTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.createTime" show-time />
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
					<a-typography-link @click="openForm(record, record.id)">
						{{ record.title }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'TASK_CATEGORY', record.category) }}
				</template>
				<template v-if="column.dataIndex === 'status'">
					<a-tag :color="$TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state_color', record.status)">
						{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_state', record.status) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'amount'">
					{{ record.variable.amount ? record.variable.amount : '--' }}
				</template>
			</template>
		</s-table>
	</a-card>

	<ProcessDetails @successful="tableRef.refresh()" ref="processDetails"></ProcessDetails>
</template>

<script setup name="historyTask">
	import { cloneDeep } from 'lodash-es'

	import bizTaskApi from '@/api/biz/bizTaskApi'
	import ProcessDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { ref } from 'vue'
	import tool from '@/utils/tool'

	const categoryOptions = ref([])
	categoryOptions.value = tool.dictListByPath(['APPROVAL_PROCESS', 'progress_category'])
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const processDetails = ref()
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
			title: '金额',
			dataIndex: 'amount'
		},

		{
			title: '流程状态',
			dataIndex: 'status'
		},
		{
			title: '发起人',
			dataIndex: 'promoterName'
		},
		{
			title: '流程类别',
			dataIndex: 'category'
		},
		{
			title: '完成时间',
			dataIndex: 'endTime'
		},
		{
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
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
		return bizTaskApi.bizHistoryTaskPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}

	const openForm = (record) => {
		processDetails.value.onOpen(record)
	}
</script>
