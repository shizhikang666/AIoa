<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="标题" name="title">
						<a-input v-model:value="searchFormState.title" placeholder="请输入标题" />
					</a-form-item>
				</a-col>

				<a-col :span="6" v-show="advanced">
					<a-form-item label="审批类别" name="category">
						<a-select
							placeholder="请选择审批类型"
							v-model:value="searchFormState.category"
							:options="categoryOptions"
						></a-select>
						<!--						<a-input v-model:value="searchFormState.category" placeholder="请输入流程类别" />-->
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.createTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<a @click="toggleAdvanced" style="margin-left: 8px">
						{{ advanced ? '收起' : '展开' }}
						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />
					</a>
				</a-col>
			</a-row>
		</a-form>
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			:alert="options.alert.show"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
			:row-selection="options.rowSelection"
		>
			<template #operator class="table-operator">
				<a-space>
					<xn-batch-delete :selectedRowKeys="selectedRowKeys" @batchDelete="deleteBatchBizCcRecords" />
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'title'">
					<a-typography-link @click="openForm(record)">
						{{ record.title }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'progress_category', record.category) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<!--						<a @click="formRef.onOpen(record)" v-if="hasPerm('bizCcRecordsEdit')">编辑</a>-->
						<!--						<a-divider type="vertical" v-if="hasPerm(['bizCcRecordsEdit', 'bizCcRecordsDelete'], 'and')"/>-->
						<a-popconfirm title="确定要删除吗？" @confirm="deleteBizCcRecords(record)">
							<a-button type="link" danger size="small">删除</a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>

	<process-details ref="saleProjectProcessRef"></process-details>
</template>

<script setup name="ccrecords">
	import { cloneDeep } from 'lodash-es'

	import bizCcRecordsApi from '@/api/biz/bizCcRecordsApi'
	import ProcessDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { ref } from 'vue'
	import tool from '@/utils/tool'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const saleProjectProcessRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}

	const categoryOptions = ref([])
	categoryOptions.value = tool.dictListByPath(['APPROVAL_PROCESS', 'progress_category'])

	const columns = [
		{
			title: '标题',
			dataIndex: 'title'
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
		return bizCcRecordsApi.bizCcRecordsPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteBizCcRecords = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizCcRecordsApi.bizCcRecordsDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchBizCcRecords = (params) => {
		bizCcRecordsApi.bizCcRecordsDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}

	const openForm = (record) => {
		saleProjectProcessRef.value.onOpen(record)
	}
</script>
