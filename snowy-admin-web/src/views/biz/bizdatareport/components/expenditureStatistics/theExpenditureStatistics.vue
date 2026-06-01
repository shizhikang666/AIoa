<script setup name="theExpenditureStatistics">
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import { useLoading } from '@/composables/useLoading'
	import { Decimal } from 'decimal.js'
	import tool from '@/utils/tool'
	import { useTemplateRef } from 'vue'
	import * as echarts from 'echarts'
	import { globalStore } from '@/store'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import { useRouter } from 'vue-router'

	const store = globalStore()
	const baseCategoryList = tool.dictListByPath('SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY')
	const currentDataSource = ref([])
	const showDetail = ref(false)
	const { orgId, startCreateTime, endCreateTime } = defineProps({
		orgId: {
			type: String
		},
		startCreateTime: {
			type: String,
			required: true
		},
		endCreateTime: {
			type: String,
			required: true
		}
	})
	const settlementAccountExpensesList = ref([])
	const countAmount = computed(() => {
		let count = new Decimal(0)
		let result = settlementAccountExpensesList.value
		let settlementCategoryMap = new Map()
		result.forEach((item) => {
			if (settlementCategoryMap.has(item.settlementCategory)) {
				let object = settlementCategoryMap.get(item.settlementCategory)
				object.countAmount = object.countAmount.add(item.amount)
			}

			count = count.add(item.amount)
		})

		return count.toString()
	})
	const amountCategoryList = computed(() => {
		let totalAmount = new Decimal(countAmount.value)
		let map = new Map()
		baseCategoryList.forEach((category) => {
			map.set(category.value, {
				title: category.label,
				key: category.value,
				countAmount: new Decimal(0),
				list: []
			})
		})
		let result = settlementAccountExpensesList.value
		result.forEach((item) => {
			if (map.has(item.settlementCategory)) {
				let object = map.get(item.settlementCategory)
				object.countAmount = object.countAmount.add(item.amount)
				object.list.push(item)
			}
		})
		const res = Array.from(map.values())

		res.forEach((item) => {
			const partialAmount = item.countAmount
			const percentage = partialAmount.dividedBy(totalAmount).times(100)
			item.percentage = Number(percentage.toFixed(2))

			if (isNaN(item.percentage)) {
				item.percentage = 0
			}
		})

		return res
	})

	const { load, error, loading } = useLoading(async () => {
		const result = await bizDataReportApi.bizSettlementAccountExpenses({
			startCreateTime,
			endCreateTime,
			orgId
		})

		let count = new Decimal(0)
		result.forEach((item) => {
			count = count.add(item.amount)
		})
		settlementAccountExpensesList.value = result
		await renderingChart()
	})
	const categoryChartDom = useTemplateRef('categoryChartDom')
	const renderingChart = async () => {
		const { themeColor, theme } = store
		await nextTick()
		let myChart = echarts.getInstanceByDom(categoryChartDom.value.$el)
		if (!myChart) {
			myChart = echarts.init(categoryChartDom.value.$el)
		}
		const valuesArray = amountCategoryList.value
		const option = {
			grid: {
				left: '20%', // 调整左侧边距，增加 Y 轴宽度
				right: '20%'
			},
			darkMode: theme === 'realDark',
			tooltip: {
				trigger: 'axis',

				axisPointer: {
					type: 'cross',
					label: {
						backgroundColor: '#6a7985'
					}
				}
			},
			color: themeColor,
			xAxis: {
				type: 'category',
				data: valuesArray.map((v) => v.title)
			},
			yAxis: {
				type: 'value'
			},
			series: [
				{
					data: valuesArray.map((v) => v.countAmount.toString()),
					type: 'line'
				}
			]
		}
		option && myChart.setOption(option)
	}
	watch(store, () => {
		renderingChart()
	})

	watchEffect(async () => {
		await load()
	})
	const router = useRouter()
	const openDetail = (item) => {
		router.push({
			path: '/biz/bizexpenditurerecord',
			query: {
				startPayerTime: startCreateTime,
				endPayerTime: endCreateTime,
				settlementCategory: item.key,
				orgId: orgId
			}
		})

		// showDetail.value = true
		// currentDataSource.value = item.list
	}

	const columns = [
		{
			title: '支出账户',
			dataIndex: 'accountName',
			key: 'accountName',
			ellipsis: true
		},
		{
			title: '金额',
			dataIndex: 'amount',
			key: 'amount',
			width: '100px'
		},
		{
			title: '付款时间',
			dataIndex: 'payerTime',
			key: 'payerTime'
		},
		{
			title: '收款人',
			dataIndex: 'payer',
			key: 'payer'
		},
		{
			title: '备注',
			dataIndex: 'remark',
			key: 'remark',
			ellipsis: true
		}
	]
</script>

<template>
	<a-spin :spinning="loading">
		<br />
		<template v-if="!error">
			<a-card>
				<a-statistic title="支出总计" :value="countAmount" style="margin-right: 50px" />
				<a-row v-for="item in amountCategoryList" :key="item.key">
					<a-typography-link @click="openDetail(item)" style="display: block; width: 100%">
						<a-typography-text>{{ item.title }}({{ item.countAmount.toString() }})</a-typography-text>
						<a-progress :title="item.title" :percent="item.percentage" />
					</a-typography-link>
				</a-row>
			</a-card>
			<br />
			<a-card style="height: 300px" ref="categoryChartDom"></a-card>
		</template>
		<error-result @reload="load" v-else></error-result>
	</a-spin>

	<a-modal width="900px" v-model:open="showDetail">
		<a-table :columns="columns" :data-source="currentDataSource">
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'amount'">
					<a-typography-text type="danger">-{{ record.amount }}</a-typography-text>
				</template>
			</template>
		</a-table>
	</a-modal>
</template>

<style scoped></style>
