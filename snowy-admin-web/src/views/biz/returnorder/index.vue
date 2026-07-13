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
			:row-selection="options.rowSelection"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="formRef.onOpen()" v-if="hasPerm('returnOrderAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						新增
					</a-button>
					<xn-batch-delete
						v-if="hasPerm('returnOrderBatchDelete')"
						:selectedRowKeys="selectedRowKeys"
						@batchDelete="deleteBatchReturnOrder"
					/>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'businessState'">
					<a-tag :color="businessStateMap[record.businessState]?.color">
						{{ businessStateMap[record.businessState]?.label || record.businessState }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'refundProgress'">
					{{ record.refundRequired ? `${record.refundAmount || 0} / ${record.amount || 0}` : '无需退款' }}
				</template>
				<template v-if="column.dataIndex === 'refundRequired'">
					<a-tag :color="record.refundRequired ? 'blue' : 'default'">
						{{ record.refundRequired ? '需要退款' : '无需退款' }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'projectName'">
					<a-typography-link @click="projectDetailsRef.onOpen({ id: record.projectId })">
						{{ record.projectName }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'state'">
					<a-tag :color="$TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status_Color', record.state)">
						{{ $TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status', record.state) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link @click="processDetailsRef.onOpen({ instanceId: record.processId })">
						{{ record.processId }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-popconfirm
							v-if="record.warehouseState === 'WAIT_RECEIVE' && record.canWarehouseReceive"
							:title="
								record.refundRequired
									? '确认仓库已收到退货？确认后将增加库存并进入财务退款环节。'
									: '确认仓库已收到退货？确认后将增加库存并完成退货，无需财务退款。'
							"
							@confirm="warehouseReceive(record)"
						>
							<a>确认收货</a>
						</a-popconfirm>
						<a @click="formRef.onOpen(record)" v-if="!record.processId && record.warehouseState === 'WAIT_RECEIVE' && hasPerm('returnOrderEdit')">编辑</a>
						<a-popconfirm v-if="!record.processId && record.warehouseState === 'WAIT_RECEIVE'" title="确定要删除吗？" @confirm="deleteReturnOrder(record)">
							<a-button type="link" danger size="small" v-if="hasPerm('returnOrderDelete')">删除</a-button>
						</a-popconfirm>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<Form ref="formRef" @successful="tableRef.refresh()" />
	<processDetails ref="processDetailsRef"></processDetails>
	<projectDetails ref="projectDetailsRef"></projectDetails>
</template>

<script setup name="returnorder">
	import { cloneDeep } from 'lodash-es'
	import Form from './form.vue'
	import returnOrderApi from '@/api/biz/returnOrderApi'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import projectDetails from '@/views/biz/saleproject/detail.vue'
	import { useTemplateRef } from 'vue'

	const projectDetailsRef = useTemplateRef('projectDetailsRef')
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	const processDetailsRef = useTemplateRef('processDetailsRef')
	const businessStateMap = {
		WAIT_WAREHOUSE_RECEIPT: { label: '待仓库收货', color: 'orange' },
		WAIT_FINANCE_REFUND: { label: '待财务退款', color: 'blue' },
		PARTIALLY_REFUNDED: { label: '部分退款', color: 'cyan' },
		COMPLETED: { label: '已完成', color: 'green' }
	}
	// 查询区域显示更多控制
	const advanced = ref(false)
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
			dataIndex: 'businessState',
			width: '120px'
		},
		{
			title: '退款处理',
			dataIndex: 'refundRequired',
			width: '110px'
		},
		{
			title: '指定财务',
			dataIndex: 'treasurerName',
			customRender: ({ text }) => text || '-'
		},
		{
			title: '已退款/退货金额',
			dataIndex: 'refundProgress',
			width: '150px'
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
		},
		{
			title: '操作',
			dataIndex: 'action',
			fixed: 'right',
			width: '180px'
		}
	]
	// 操作栏通过权限判断是否显示

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
		return returnOrderApi.returnOrderPage(Object.assign(parameter, searchFormParam)).then((data) => {
			return data
		})
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	// 删除
	const deleteReturnOrder = (record) => {
		let params = [
			{
				id: record.id
			}
		]
		returnOrderApi.returnOrderDelete(params).then(() => {
			tableRef.value.refresh(true)
		})
	}
	// 批量删除
	const deleteBatchReturnOrder = (params) => {
		returnOrderApi.returnOrderDelete(params).then(() => {
			tableRef.value.clearRefreshSelected()
		})
	}
	const warehouseReceive = (record) => {
		return returnOrderApi.returnOrderWarehouseReceive({ id: record.id }).then(() => {
			tableRef.value.refresh(true)
		})
	}
</script>
