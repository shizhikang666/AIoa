<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
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
					<a-form-item label="创建时间" name="createTime">
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

				<template v-if="column.dataIndex === 'orgName'">
					{{ (record.startOrgTree&&record.startOrgTree.length) ? record.startOrgTree[0].label : '' }}
				</template>
				<template v-if="column.dataIndex === 'amount'">
					{{ record.variable.amount ? record.variable.amount : '--' }}
				</template>
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPath('APPROVAL_PROCESS', 'TASK_CATEGORY', record.category) }}
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-button type="link" @click="openForm(record, record.id)" success>查看详情</a-button>
						<a-button type="link" @click="submit(record, false)" danger>拒绝</a-button>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>

	<process-details @successful="tableRef.refresh()" ref="processDetails"></process-details>
</template>

<script setup name="myTask">
	import { cloneDeep } from 'lodash-es'
	import ProcessDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import bizTaskApi from '@/api/biz/bizTaskApi'
	import { ref } from 'vue'
	import tool from '@/utils/tool'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
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
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '发起人',
			dataIndex: 'promoterName',
			ellipsis: true,
			width: 100
		},
		{
			title: '所属组织机构',
			dataIndex: 'orgName',
			ellipsis: true
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
			align: 'center'
		}
	]

	const categoryOptions = ref([])
	categoryOptions.value = tool.dictListByPath(['APPROVAL_PROCESS', 'progress_category'])
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
		return bizTaskApi.bizTaskPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}

	const openForm = (record, taskId) => {
		processDetails.value.onOpen(record, taskId)
	}

	const submit = async (record, flag) => {
		try {
			const res = await bizTaskApi.reject({
				id: record.id
			})
		} catch (e) {
		} finally {
			tableRef.value.refresh()
		}
	}
</script>
