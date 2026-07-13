<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<!--				<a-col :span="6">-->
				<!--					<a-form-item label="请假类型" name="category">-->
				<!--						<a-select-->
				<!--							:options="categoryOptions"-->
				<!--							v-model:value="searchFormState.category"-->
				<!--							placeholder="请选择请假类型"-->
				<!--						/>-->
				<!--					</a-form-item>-->
				<!--				</a-col>-->
				<a-col :span="6">
					<a-form-item label="天数" name="amount">
						<a-input v-model:value="searchFormState.amount" placeholder="请输入天数" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="请假人" name="name">
						<a-input v-model:value="searchFormState.name" placeholder="请假人" />
					</a-form-item>
				</a-col>

				<a-col :span="12">
					<a-form-item label="开始日期" name="startTime">
						<a-range-picker v-model:value="searchFormState.startTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="12">
					<a-form-item label="结束日期" name="endTime">
						<a-range-picker v-model:value="searchFormState.endTime" show-time />
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
			:row-selection="rowSelection"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #operator class="table-operator"> </template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPath('vacation', 'GoOut', record.category) }}
				</template>
				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link
						v-if="record.processId && record.processId !== 'Process_sys'"
						@click="processDetailsRef.onOpen({ instanceId: record.processId })"
					>
						{{ record.processId }}
					</a-typography-link>
				</template>
			</template>
		</s-table>
	</a-card>

	<processDetails ref="processDetailsRef"></processDetails>
</template>

<script setup name="bizLeaveApplication">
	import { ref, computed } from 'vue'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { PlusOutlined, UpOutlined, DownOutlined } from '@ant-design/icons-vue'

	import bizLeaveApplicationApi from '@/api/biz/bizLeaveApplicationApi'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { useOrg } from '@/composables/useOrg'

	const { rowSelection, defaultSearchFrom } = defineProps({
		rowSelection: {
			type: Object
		},
		defaultSearchFrom: {
			type: Object,
			default: () => {
				return {}
			}
		}
	})
	// 组织树数据
	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData()

	// 搜索表单相关
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()

	const processDetailsRef = ref()

	// 工具配置
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }

	// 高级搜索控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}

	// 表格列定义
	const columns = [
		{
			title: '请假人',
			dataIndex: 'name'
		},
		{
			title: '流程ID',
			dataIndex: 'processId',
			ellipsis: true
		},
		{
			title: '请假类型',
			dataIndex: 'category'
		},
		{
			title: '天数',
			dataIndex: 'amount'
		},
		{
			title: '请假原因',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '请假开始日期',
			dataIndex: 'startTime',
			ellipsis: true
		},
		{
			title: '请假结束日期',
			dataIndex: 'endTime',
			ellipsis: true
		}
	]

	// 选中行
	const selectedRowKeys = ref([])

	// 加载数据
	const loadData = (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)

		// 处理时间范围查询
		if (searchFormParam.startTime) {
			searchFormParam.startStartTime = searchFormParam.startTime[0]
			searchFormParam.endStartTime = searchFormParam.startTime[1]
			delete searchFormParam.startTime
		}

		if (searchFormParam.endTime) {
			searchFormParam.startEndTime = searchFormParam.endTime[0]
			searchFormParam.endEndTime = searchFormParam.endTime[1]
			delete searchFormParam.endTime
		}

		return bizLeaveApplicationApi
			.bizLeaveApplicationMyPage(Object.assign(parameter, searchFormParam, defaultSearchFrom))
			.then((data) => {
				return data
			})
	}

	// 重置搜索
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}

	// 删除单条记录
	const deleteBizLeaveApplication = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		bizLeaveApplicationApi.bizLeaveApplicationDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}

	// 批量删除
	const deleteBatchBizLeaveApplication = (params) => {
		bizLeaveApplicationApi.bizLeaveApplicationDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}

	// 请假类型选项（合并请假和外出类型）
	const categoryOptions = computed(() => {
		let goOutOptions = tool.dictListByPath('vacation', 'GoOut') || []
		return [...goOutOptions]
	})
</script>

<style scoped></style>
