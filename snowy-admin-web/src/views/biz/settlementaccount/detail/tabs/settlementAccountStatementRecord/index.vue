<template>
	<a-card :bordered="false">
		<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
			<a-row :gutter="24">
				<a-col :span="6" v-show="advanced">
					<a-form-item label="结算分类" name="settlementCategory">
						<a-select
							v-model:value="searchFormState.settlementCategory"
							placeholder="请选择结算分类"
							:options="settlementCategoryOptions"
						/>
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="付款时间" name="payerTime">
						<a-range-picker v-model:value="searchFormState.payerTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6" v-show="advanced">
					<a-form-item label="创建时间" name="createTime">
						<a-range-picker v-model:value="searchFormState.createTime" show-time />
					</a-form-item>
				</a-col>
				<a-col :span="6">
					<a-button type="primary" @click="search">查询</a-button>
					<a-button style="margin: 0 8px" @click="reset">重置</a-button>
				</a-col>
			</a-row>
		</a-form>
		<!--		<s-table-->
		<!--			ref="tableRef"-->
		<!--			:data="load"-->
		<!--			bordered-->
		<!--			:row-key="(record) => record.id"-->
		<!--			:tool-config="toolConfig"-->
		<!--			:columns="columns"-->
		<!--		>-->
		<!--			<template #operator class="table-operator"></template>-->
		<!--			<template #bodyCell="{ column, record }">-->
		<!--				<template v-if="column.dataIndex === 'processId'">-->
		<!--					<a-typography-link @click="processDetailsRef.onOpen({ instanceId: record.processId })">-->
		<!--						{{ record.processId }}-->
		<!--					</a-typography-link>-->
		<!--				</template>-->
		<!--				<template v-if="column.dataIndex === 'settlementType'">-->
		<!--					{{ $TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_TYPE', record.settlementType) }}-->
		<!--				</template>-->
		<!--				<template v-if="column.dataIndex === 'amount'">-->
		<!--					<a-typography-text :type="record.settlementType === 'INCOME' ? 'success' : 'danger'">-->
		<!--						{{ (record.settlementType === 'INCOME' ? '+' : '-') + record.amount }}-->
		<!--					</a-typography-text>-->
		<!--				</template>-->
		<!--				<template v-if="column.dataIndex === 'settlementCategory'">-->
		<!--					{{-->
		<!--						$TOOL.dictTypeDataByPath(-->
		<!--							'SETTLEMENT_ACCOUNT',-->
		<!--							'SETTLEMENT_CATEGORY',-->
		<!--							record.settlementType === 'INCOME' ? 'INCOME_CATEGORY' : 'PAY_CATEGORY',-->
		<!--							record.settlementCategory-->
		<!--						)-->
		<!--					}}-->
		<!--				</template>-->
		<!--			</template>-->
		<!--		</s-table>-->

		<a-table :data-source="searchDataSource" :columns="columns">
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'processId'">
					<a-typography-link @click="processDetailsRef.onOpen({ instanceId: record.processId })">
						{{ record.processId }}
					</a-typography-link>
				</template>
				<template v-if="column.dataIndex === 'settlementType'">
					{{ $TOOL.dictTypeDataByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_TYPE', record.settlementType) }}
				</template>
				<template v-if="column.dataIndex === 'amount'">
					<a-typography-text :type="record.settlementType === 'INCOME' ? 'success' : 'danger'">
						{{ (record.settlementType === 'INCOME' ? '+' : '-') + record.amount }}
					</a-typography-text>
				</template>
				<template v-if="column.dataIndex === 'settlementCategory'">
					{{
						$TOOL.dictTypeDataByPath(
							'SETTLEMENT_ACCOUNT',
							'SETTLEMENT_CATEGORY',
							record.settlementType === 'INCOME' ? 'INCOME_CATEGORY' : 'PAY_CATEGORY',
							record.settlementCategory
						)
					}}
				</template>
			</template>
		</a-table>
	</a-card>
	<processDetails ref="processDetailsRef" />
</template>

<script setup name="settlementAccountStatementRecord">
	import tool from '@/utils/tool'
	import settlementAccountPaymentApi from '@/api/biz/settlementAccountPaymentApi'
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import { useTemplateRef } from 'vue'
	import { useLoading } from '@/composables/useLoading'
	import { cloneDeep } from 'lodash-es'
	import settlementAccountApi from '@/api/biz/settlementAccountApi'
	import { Decimal } from 'decimal.js'
	import dayjs from '@/utils/dayjs'

	const searchFormState = ref({})
	const searchFormRef = ref()
	const tableRef = ref()
	const toolConfig = { refresh: true, height: true, columnSetting: true, striped: false }

	const processDetailsRef = useTemplateRef('processDetailsRef')
	const { accountId } = defineProps({
		accountId: {
			required: true,
			type: String
		}
	})

	// 查询区域显示更多控制
	const advanced = ref(true)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const columns = [
		{
			title: '流程实例编号',
			dataIndex: 'processId'
		},
		{
			title: '结算分类',
			dataIndex: 'settlementCategory'
		},
		{
			title: '收支分类',
			dataIndex: 'settlementType'
		},

		{
			title: '操作前金额',
			dataIndex: 'beforeAmount'
		},
		{
			title: '金额',
			dataIndex: 'amount'
		},
		{
			title: '操作后金额',
			dataIndex: 'afterAmount'
		},
		{
			title: '收款时间',
			dataIndex: 'payerTime'
		}
		// {
		// 	title: '创建时间',
		// 	dataIndex: 'createTime'
		// }
	]
	const selectedRowKeys = ref([])
	const dataSource = ref([])
	const searchDataSource = ref([])

	const { load, loading, error } = useLoading(async () => {
		const detail = await settlementAccountApi.settlementAccountDetail({ id: accountId })
		let amount = new Decimal(detail.initialAmount)
		let result = await settlementAccountPaymentApi.settlementAccountPaymentList(
			Object.assign({
				accountId: accountId,
				sortField: 'payerTime',
				sortOrder: 'descend'
			})
		)

		result = result.reverse()

		result = result.map((v) => {
			v.beforeAmount = amount.toString()
			if (v.settlementType === 'INCOME') {
				amount = amount.add(new Decimal(v.amount))
			} else {
				amount = amount.sub(new Decimal(v.amount))
			}
			v.afterAmount = amount.toString()

			return v
		})

		searchDataSource.value = dataSource.value = result.reverse()
	})

	const search = () => {
		const searchFormParam = cloneDeep(searchFormState.value)
		// payerTime范围查询条件重载
		if (searchFormParam.payerTime) {
			searchFormParam.startPayerTime = searchFormParam.payerTime[0]
			searchFormParam.endPayerTime = searchFormParam.payerTime[1]
			delete searchFormParam.payerTime
		}
		// createTime范围查询条件重载
		if (searchFormParam.createTime) {
			searchFormParam.startCreateTime = searchFormParam.createTime[0]
			searchFormParam.endCreateTime = searchFormParam.createTime[1]
			delete searchFormParam.createTime
		}

		searchDataSource.value = dataSource.value.filter((v) => {
			if (searchFormParam.settlementCategory) {
				if (v.settlementCategory !== searchFormParam.settlementCategory) {
					return false
				}
			}

			if (searchFormParam.startCreateTime && searchFormParam.endCreateTime) {
				const date = dayjs(v.createTime)
				const start = dayjs(searchFormParam.startCreateTime)
				const end = dayjs(searchFormParam.endCreateTime)

				const isWithinRange = date.isAfter(start) && date.isBefore(end)

				if (!isWithinRange) {
					return false
				}
			}

			if (searchFormParam.startPayerTime && searchFormParam.endPayerTime) {
				const date = dayjs(v.payerTime)
				const start = dayjs(searchFormParam.startPayerTime)
				const end = dayjs(searchFormParam.endPayerTime)
				const isWithinRange = date.isAfter(start) && date.isBefore(end)
				if (!isWithinRange) {
					return false
				}
			}

			return true
		})
	}

	// const loadData = async (parameter) => {
	// 	const searchFormParam = cloneDeep(searchFormState.value)
	// 	// payerTime范围查询条件重载
	// 	if (searchFormParam.payerTime) {
	// 		searchFormParam.startPayerTime = searchFormParam.payerTime[0]
	// 		searchFormParam.endPayerTime = searchFormParam.payerTime[1]
	// 		delete searchFormParam.payerTime
	// 	}
	// 	// createTime范围查询条件重载
	// 	if (searchFormParam.createTime) {
	// 		searchFormParam.startCreateTime = searchFormParam.createTime[0]
	// 		searchFormParam.endCreateTime = searchFormParam.createTime[1]
	// 		delete searchFormParam.createTime
	// 	}
	//
	// 	return {
	// 		current: 1,
	// 		pages: 2,
	// 		records: dataSource.value,
	// 		size: 10,
	// 		total: 22
	// 	}
	// }

	// 重置
	const reset = () => {
		searchFormRef.value.resetFields()

		search()
	}

	load()

	const settlementCategoryOptions = [
		...tool.dictListByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'INCOME_CATEGORY'),
		...tool.dictListByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY')
	]
</script>
