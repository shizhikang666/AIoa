<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6" v-if="!disableSearchFromKey.settlementStatus">
					<a-form-item label="结算状态" name="settlementStatus">
						<a-select
							allow-clear
							v-model:value="searchFormState.settlementStatus"
							placeholder="请输入结算状态"
							:options="settlementStatusOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-if="!disableSearchFromKey.storageStatus">
					<a-form-item label="入库状态" name="storageStatus">
						<a-input v-model:value="searchFormState.storageStatus" placeholder="请输入入库状态" />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-if="!disableSearchFromKey.supplierId">
					<a-form-item label="供应商" name="supplierId">
						<a-select
							:options="supplierList"
							v-model:value="searchFormState.supplierId"
							placeholder="请输入供应商编号"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="12">
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
				<a-col :span="12" v-show="advanced" v-if="!disableSearchFromKey.createTime">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker value-format="YYYY-MM-DD HH:mm:ss" v-model:value="searchFormState.createTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="tableRef.refresh()">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					<!--					<a @click="toggleAdvanced" style="margin-left: 8px">-->
					<!--						{{ advanced ? '收起' : '展开' }}-->
					<!--						<component :is="advanced ? 'up-outlined' : 'down-outlined'"/>-->
					<!--					</a>-->
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
			:rowSelection="rowSelection"
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'title'">
					<a-badge :count="record.processIdList.length">
						<a-typography-link @click="bizPurchaseOrderDetailsRef.onOpen(record)"
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
			</template>
		</s-table>
	</a-card>
	<startProcureFlowForm ref="startProcureFlowFormRef"></startProcureFlowForm>
	<processDetails ref="processDetailsRef"></processDetails>
	<bizPurchaseOrderDetails ref="bizPurchaseOrderDetailsRef"></bizPurchaseOrderDetails>
</template>
<script setup name="bizPurchaseOrder">
	import { cloneDeep } from 'lodash-es'
	const settlementStatusOptions = tool.dictListByPath(['PURCHASE_ORDER', 'SETTLEMENT_STATUS'])
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
					supplierId: false,
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
	import bizPurchaseOrderDetails from '../details/index.vue'
	import startProcureFlowForm from '@/views/biz/bizprocess/processForm/procure/startProcureFlowForm.vue'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import supplierApi from '@/api/biz/supplierApi'
	import { useTemplateRef } from 'vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import tool from '@/utils/tool'

	const startProcureFlowFormRef = useTemplateRef('startProcureFlowFormRef')
	const processDetailsRef = useTemplateRef('processDetailsRef')
	const bizPurchaseOrderDetailsRef = useTemplateRef('bizPurchaseOrderDetailsRef')
	const searchFormState = ref(defaultSearchFrom)
	const searchFormRef = ref()
	const tableRef = ref()
	const formRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制
	const advanced = ref(true)
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
		// {
		// 	title: '流程实例ID',
		// 	dataIndex: 'instanceId'
		// },
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
			.bizPurchaseOrderPage(Object.assign(parameter, searchFormParam, defaultSearchFrom))
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
</script>

<style scoped></style>
