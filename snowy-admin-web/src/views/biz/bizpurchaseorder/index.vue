<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6">
					<a-form-item label="产品名称" name="productName">
						<a-input v-model:value="searchFormState.productName" />
					</a-form-item>
				</a-col>

				<a-col :span="6">
					<a-form-item label="结算状态" name="settlementStatus">
						<a-select
							v-model:value="searchFormState.settlementStatus"
							placeholder="请输入结算状态"
							:options="settlementStatusOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="入库状态" name="storageStatus">
						<a-select v-model:value="searchFormState.storageStatus" placeholder="入库状态" :options="storageOptions" />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-form-item label="金额范围">
						<a-row>
							<a-space>
								<div>
									<a-form-item label="" name="minAmount">
										<XnCurrencyInput :min="0" v-model:value="searchFormState.minAmount" placeholder="请输入最小金额" />
									</a-form-item>
								</div>

								<div>
									<a-form-item label="" name="maxAmount">
										<XnCurrencyInput :min="0" v-model:value="searchFormState.maxAmount" placeholder="请输入最大金额" />
									</a-form-item>
								</div>
							</a-space>
						</a-row>
					</a-form-item>
				</a-col>

				<a-col :span="7">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.createTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<!--					<a @click="toggleAdvanced" style="margin-left: 8px">-->
					<!--						{{ advanced ? '收起' : '展开' }}-->
					<!--						<component :is="advanced ? 'up-outlined' : 'down-outlined'" />-->
					<!--					</a>-->
				</a-col>
			</a-row>
		</a-form>
		<br />
		<s-table
			ref="tableRef"
			:columns="columns"
			:data="loadData"
			bordered
			:row-key="(record) => record.id"
			:tool-config="toolConfig"
		>
			<template #operator class="table-operator">
				<a-space>
					<a-button type="primary" @click="startProcureFlowFormRef.onOpen()" v-if="hasPerm('bizPurchaseOrderAdd')">
						<template #icon>
							<plus-outlined />
						</template>
						申请采购
					</a-button>
					<a-button type="primary" @click="procureInWarehouseFormRef.onOpen()">
						<template #icon>
							<FormOutlined />
						</template>
						一键入库
					</a-button>
					<a-button type="primary" @click="purchaseOrderExportRef.onOpen()">
						<template #icon>
							<FormOutlined />
						</template>
						导出
					</a-button>
				</a-space>
			</template>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'title'">
					<a-badge :count="record.processIdList.length">
						<a-typography-link
							type="danger"
							delete
							v-if="record.settlementStatus === 'Canceled'"
							@click="bizPurchaseOrderDetailsRef.onOpen(record)"
							>{{ record.title }}
						</a-typography-link>

						<a-typography-link v-else @click="bizPurchaseOrderDetailsRef.onOpen(record)"
							>{{ record.title }}
						</a-typography-link>
					</a-badge>
				</template>
				<template v-if="column.dataIndex === 'settlementStatus'">
					<a-tag
						:color="$TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'SETTLEMENT_STATUS_COLOR', record.settlementStatus)"
					>
						{{ $TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'SETTLEMENT_STATUS', record.settlementStatus) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'storageStatus'">
					<a-tag :color="$TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'STORAGE_STATUS_COLOR', record.storageStatus)">
						{{ $TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'STORAGE_STATUS', record.storageStatus) }}
					</a-tag>
				</template>
				<template v-if="column.dataIndex === 'instanceId'">
					<a-typography-link @click="processDetailsRef.onOpen(record)">{{ record.instanceId }} </a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'action'">
					<a-space>
						<a-typography-link
							:disabled="
								record.settlementStatus === 'COMPLETED' ||
								record.storageStatus === 'IN_WAREHOUSE' ||
								record.settlementStatus === 'Canceled'
							"
							@click="cancel(record)"
							>作废
						</a-typography-link>
						<a-typography-link
							:disabled="
								record.settlementStatus === 'COMPLETED' ||
								record.storageStatus === 'IN_WAREHOUSE' ||
								record.settlementStatus === 'Canceled'
							"
							@click="procureInWarehouseOneFormRef.onOpen(record)"
						>
							入库</a-typography-link
						>
						<a-dropdown :disabled="record.settlementStatus === 'Canceled'">
							<a class="ant-dropdown-link">
								{{ $t('common.more') }}
								<DownOutlined />
							</a>
							<template #overlay>
								<a-menu>
									<a-menu-item>
										<a-anchor-link
											v-if="!(record.settlementStatus === 'COMPLETED' || record.settlementStatus === 'Canceled')"
											@click="bizPurchaseOrderFormRef.onOpen(record)"
											>编辑
										</a-anchor-link>
										<a-anchor-link v-else @click="auditRemediationRef.onOpen(record)">审计修复 </a-anchor-link>
									</a-menu-item>
								</a-menu>
							</template>
						</a-dropdown>
					</a-space>
				</template>
			</template>
		</s-table>
	</a-card>
	<startProcureFlowForm ref="startProcureFlowFormRef"></startProcureFlowForm>
	<processDetails ref="processDetailsRef"></processDetails>
	<bizPurchaseOrderDetails ref="bizPurchaseOrderDetailsRef"></bizPurchaseOrderDetails>
	<bizPurchaseOrderForm @successful="tableRef.refresh()" ref="bizPurchaseOrderFormRef"></bizPurchaseOrderForm>
	<procureInWarehouseForm @successful="tableRef.refresh()" ref="procureInWarehouseFormRef"></procureInWarehouseForm>
	<procureInWarehouseOneForm
		@successful="tableRef.refresh()"
		ref="procureInWarehouseOneFormRef"
	></procureInWarehouseOneForm>
	<auditRemediation @successful="tableRef.refresh()" ref="auditRemediationOneRef"></auditRemediation>
	<purchaseOrderExport ref="purchaseOrderExportRef"></purchaseOrderExport>
</template>

<script setup name="bizpurchaseorder">
	import { cloneDeep } from 'lodash-es'
	import bizPurchaseOrderForm from './form.vue'
	import { App } from 'ant-design-vue'

	const auditRemediationRef = ref()
	const { message, modal, notification } = App.useApp()
	import purchaseOrderExport from './export/index.vue'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import bizPurchaseOrderDetails from './details/index.vue'
	import startProcureFlowForm from '@/views/biz/bizprocess/processForm/procure/startProcureFlowForm.vue'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import supplierApi from '@/api/biz/supplierApi'
	import { useTemplateRef } from 'vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import tool from '@/utils/tool'
	import auditRemediation from './auditRemediation.vue'

	import ProcureInWarehouseForm from '@/views/biz/bizpurchaseorder/procureInWarehouseForm.vue'
	import procureInWarehouseOneForm from '@/views/biz/bizpurchaseorder/procureInWarehouseOneForm.vue'

	const procureInWarehouseOneFormRef = ref()
	const bizPurchaseOrderFormRef = useTemplateRef('bizPurchaseOrderFormRef')
	const procureInWarehouseFormRef = useTemplateRef('procureInWarehouseFormRef')
	const settlementStatusOptions = tool.dictListByPath(['PURCHASE_ORDER', 'SETTLEMENT_STATUS'])
	const storageOptions = tool.dictListByPath(['PURCHASE_ORDER', 'STORAGE_STATUS'])
	const startProcureFlowFormRef = useTemplateRef('startProcureFlowFormRef')
	const processDetailsRef = useTemplateRef('processDetailsRef')
	const bizPurchaseOrderDetailsRef = useTemplateRef('bizPurchaseOrderDetailsRef')
	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const purchaseOrderExportRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const supplierList = ref([])
	supplierApi.supplierList().then((res) => {
		supplierList.value = res.map((v) => {
			return { label: v.name, value: v.id }
		})
	})

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
			title: '结算状态',
			dataIndex: 'settlementStatus'
		},
		{
			title: '入库状态',
			dataIndex: 'storageStatus'
		},
		// {
		// 	title: '供应商',
		// 	dataIndex: 'supplierName'
		// },
		{
			title: '流程实例ID',
			dataIndex: 'instanceId',
			ellipsis: true
		},
		{
			title: '预期采购时间',
			dataIndex: 'desirePurchaseDate'
		},
		{
			title: '金额',
			dataIndex: 'amount'
		},

		{
			title: '创建时间',
			dataIndex: 'createTime'
		},
		{
			title: '操作',
			dataIndex: 'action',
			align: 'center',
			width: '200px'
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
		const result = await bizPurchaseOrderApi
			.bizPurchaseOrderPage(Object.assign(parameter, searchFormParam, {}))
			.then((data) => {
				return data
			})

		const processInfo = await bizProcessApi.bizProcessQuery({
			variableName: 'objectId',
			variable: result.records
				.map((value, index) => {
					return value.id
				})
				.join(',')
		})

		const processMap = {}

		processInfo.forEach((item) => {
			processMap[item.variable] = item.processIdList
		})

		result.records.forEach((v) => {
			v.processIdList = processMap[v.id]
		})

		return result
	}
	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()
		tableRef.value.refresh(true)
	}
	const cancel = (record) => {
		modal.confirm({
			title: '确认作废?',
			content: '确认要作废吗',
			onOk: async () => {
				await bizPurchaseOrderApi.bizPurchaseOrderCancel({
					id: record.id
				})

				tableRef.value.refresh(true)
			},
			onCancel() {},
			class: 'test'
		})
	}
</script>
