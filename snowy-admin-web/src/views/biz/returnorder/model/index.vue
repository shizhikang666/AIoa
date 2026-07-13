<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="退回金额" name="amount">
						<a-input v-model:value="searchFormState.amount" placeholder="请输入退回金额" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="备注" name="remark">
						<a-input v-model:value="searchFormState.remark" placeholder="请输入备注" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="退回仓库" name="warehousesId">
						<a-input v-model:value="searchFormState.warehousesId" placeholder="请输入退回仓库" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker v-model:value="searchFormState.createTime" show-time />
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
			:row-selection="effectiveRowSelection"
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'businessState'">
					<a-tag :color="businessStateMap[record.businessState]?.color">
						{{ businessStateMap[record.businessState]?.label || record.businessState }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'refundRequired'">
					<a-tag :color="record.refundRequired ? 'blue' : 'default'">
						{{ record.refundRequired ? '需要退款' : '无需退款' }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'state'">
					<a-tag :color="$TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status_Color', record.state)">
						{{ $TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status', record.state) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'projectName'">
					<a-badge :count="record.processIdList.length">
						<a-typography-link @click="projectDetailsRef.onOpen({ id: record.projectId })">
							{{ record.projectName }}
						</a-typography-link>
					</a-badge>
				</template>

				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link @click="processDetailsRef.onOpen({ instanceId: record.processId })">
						{{ record.processId }}
					</a-typography-link>
				</template>
			</template>
		</s-table>
	</a-card>
	<projectDetails ref="projectDetailsRef"></projectDetails>
	<processDetails ref="processDetailsRef"></processDetails>
</template>
<script setup name="bizReturnOrderModel">
	import { cloneDeep } from 'lodash-es'
	import projectDetails from '@/views/biz/saleproject/detail.vue'

	const { ignoreIdList, defaultSearchFrom, disableSearchFromKey, rowSelection } = defineProps({
		ignoreIdList: {
			type: Array, // 使用 Array 而不是 []
			default: () => [] // 默认值为一个空数组
		},
		defaultSearchFrom: {
			type: Object,
			default: () => {
				return {}
			}
		},
		disableSearchFromKey: {
			type: Object,
			default: () => {
				return {
					settlementStatus: false,
					storageStatus: false,
					createTime: false
				}
			}
		},
		rowSelection: {
			type: Object,
			default: () => {
				return {}
			}
		}
	})

	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import returnOrderApi from '@/api/biz/returnOrderApi'
	import { useTemplateRef } from 'vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'

	const projectDetailsRef = useTemplateRef('projectDetailsRef')
	const processDetailsRef = useTemplateRef('processDetailsRef')

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const businessStateMap = {
		WAIT_WAREHOUSE_RECEIPT: { label: '待仓库收货', color: 'orange' },
		WAIT_FINANCE_REFUND: { label: '待财务退款', color: 'blue' },
		PARTIALLY_REFUNDED: { label: '部分退款', color: 'cyan' },
		COMPLETED: { label: '已完成', color: 'green' }
	}
	const effectiveRowSelection = computed(() => ({
		...rowSelection,
		getCheckboxProps: (record) => ({
			disabled: Number(record.refundableAmount || 0) <= 0
		})
	}))
	// 查询区域显示更多控制
	const advanced = ref(true)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}

	const columns = [
		{
			title: '项目名称',
			dataIndex: 'projectName'
		},
		{
			title: '退回金额',
			dataIndex: 'amount'
		},
		{
			title: '业务状态',
			dataIndex: 'businessState'
		},
		{
			title: '退款处理',
			dataIndex: 'refundRequired'
		},
		{
			title: '指定财务',
			dataIndex: 'treasurerName',
			customRender: ({ text }) => text || '-'
		},
		{
			title: '可退款金额',
			dataIndex: 'refundableAmount'
		},
		{
			title: '已退款金额',
			dataIndex: 'refundAmount'
		},
		{
			title: '流程编号',
			dataIndex: 'processId',
			ellipsis: true
		},
		{
			title: '备注',
			dataIndex: 'remark'
		},
		{
			title: '退回仓库',
			dataIndex: 'warehouseName'
		},

		{
			title: '负责人',
			dataIndex: 'headName'
		},
		{
			title: '创建时间',
			dataIndex: 'createTime'
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
	const loadData = async (parameter) => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}
		const result = await returnOrderApi
			.returnOrderPage(Object.assign(parameter, searchFormParam, defaultSearchFrom))
			.then((data) => {
				return data
			})

		const processInfo = result.records.length
			? await bizProcessApi.bizProcessQuery({
					variableName: 'objectId',
					variable: result.records.map((value) => value.id).join(',')
				})
			: []

		const processMap = {}

		processInfo.forEach((item) => {
			processMap[item.variable] = item.processIdList
		})

		result.records.forEach((v) => {
			v.processIdList = processMap[v.id] || []
		})
		return result
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
</script>

<style scoped></style>
