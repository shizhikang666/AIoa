<template>
	<a-card style="width: 900px" :bordered="false">
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
				<template v-if="column.dataIndex === 'paymentRecordId'">
					<a-badge :count="record.processIdList.length">
						{{ record.paymentRecordId }}
					</a-badge>
				</template>

				<template v-if="column.dataIndex === 'playStatus'">
					{{ $TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status', record.playStatus) }}
				</template>
			</template>
		</s-table>
	</a-card>
</template>
<script setup name="bizDebitNoteModel">
	import { cloneDeep } from 'lodash-es'

	const playStatusOptions = tool.dictListByPath('SETTLEMENT_ACCOUNT', 'Settlement_Status')
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

	import bizProcessApi from '@/api/biz/bizProcessApi'
	import bizDebitNoteApi from '@/api/biz/bizDebitNoteApi'
	import tool from '@/utils/tool'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()

	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }
	// 查询区域显示更多控制

	const columns = [
		{
			title: '支出单编号',
			dataIndex: 'expenditureRecordId'
		},
		{
			title: '备注',
			dataIndex: 'remark'
		},
		{
			title: '结算状态',
			dataIndex: 'playStatus'
		},
		{
			title: '代收款金额',
			dataIndex: 'amount'
		},
		{
			title: '已结算金额',
			dataIndex: 'settlementAmount'
		},
		{
			title: '收入款时间',
			dataIndex: 'payerTime'
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
		const result = await bizDebitNoteApi
			.bizDebitNotePage(Object.assign(parameter, searchFormParam, defaultSearchFrom))
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
