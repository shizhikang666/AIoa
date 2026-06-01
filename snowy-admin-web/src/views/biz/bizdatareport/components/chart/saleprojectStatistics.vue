<script setup lang="js">
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import dayjs from '@/utils/dayjs'
	import { useLoading } from '@/composables/useLoading'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import { useTemplateRef } from 'vue'
	import * as echarts from 'echarts'
	import { globalStore } from '@/store'
	import { Decimal } from 'decimal.js'

	import { theme } from 'ant-design-vue'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const advanced = ref(false)
	const toggleAdvanced = () => {
		advanced.value = !advanced.value
	}
	const searchFormState = ref({})
	const searchFormRef = ref()
	const dataSource = defineModel('dataSource')
	const { useToken } = theme
	const { token } = useToken()

	const store = globalStore()

	const { orgId, headName, year } = defineProps({
		orgId: {
			type: String
		},
		headName: {
			type: String
		},
		year: {
			type: dayjs.type,
			default: dayjs()
		}
	})
	const chartRef = useTemplateRef('chartRef')
	// 获取当前年度的开始时间
	const saleProjectList = ref([])
	const { load, loading, error } = useLoading(async () => {
		const startOfYear = year.startOf('year')
		// 获取当前年度的结束时间
		const endOfYear = year.endOf('year')
		const startCreateTime = startOfYear.format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = endOfYear.format('YYYY-MM-DD HH:mm:ss')

		saleProjectList.value = await bizDataReportApi.bizSaleProjectDataList({
			endCreateTime,
			startCreateTime,
			orgId,
			headName
		})
		dataSource.value = saleProjectList.value
		render()
	})

	const render = async () => {
		const { themeColor, theme } = store
		await nextTick()
		let myChart = echarts.getInstanceByDom(chartRef.value.$el)
		if (!myChart) {
			myChart = echarts.init(chartRef.value.$el)
		}
		const list = saleProjectList.value
		const result = []
		for (let i = 0; i < 12; i++) {
			result[i] = {
				title: `${i + 1}月`,
				totalPrice: new Decimal(0),
				amountCollected: new Decimal(0),
				totalReturnAmount: new Decimal(0)
			}
		}
		list.forEach((item) => {
			const month = dayjs(item.completionDate).month()
			result[month].totalPrice = result[month].totalPrice.add(item.totalPrice)
			result[month].amountCollected = result[month].amountCollected.add(item.amountCollected)
			result[month].totalReturnAmount = result[month].totalReturnAmount.add(item.totalReturnAmount)
		})
		const totalPriceSum = result.reduce((sum, v) => sum.add(v.totalPrice), new Decimal(0))
		const amountCollectedSum = result.reduce((sum, v) => sum.add(v.amountCollected), new Decimal(0))
		const totalReturnAmountSum = result.reduce((sum, v) => sum.add(v.totalReturnAmount), new Decimal(0))
		const option = {
			darkMode: theme === 'realDark',
			title: {
				text: `总营业额: ${totalPriceSum}`,
				left: 10, // 右对齐
				top: 10, // 顶部对齐

				textStyle: {
					fontSize: 12,
					color: '#333' // 根据主题调整颜色
				}
			},

			tooltip: {
				trigger: 'axis',
				axisPointer: {
					type: 'shadow'
				}
			},
			legend: {
				top: 10
			},
			grid: {
				// left: '3%',
				// bottom: '3%',
				// containLabel: true
			},
			xAxis: {
				type: 'category',
				data: result.map((v) => v.title)
			},
			yAxis: {
				type: 'value',
				boundaryGap: [0, 0.01]
			},
			series: [
				{
					color: themeColor,
					name: '总营业额',
					type: 'bar',
					data: result.map((v) => v.totalPrice.toNumber())
				},
				{
					color: token.value.colorSuccess,
					name: '已收款',
					type: 'bar',
					data: result.map((v) => v.amountCollected.toNumber())
				},
				{
					color: token.value.colorError,
					name: '实际退款',
					type: 'bar',
					data: result.map((v) => v.totalReturnAmount.toNumber())
				}
			]
		}

		option && myChart.setOption(option)
	}

	watchEffect(async () => {
		await load()
	})
	watch(store, () => {
		render()
	})
</script>

<template>
	<a-card title="年度销售额统计">
		<a-spin :spinning="loading">
			<error-result @reload="load" v-if="error"></error-result>

			<template v-else>
				<a-card style="height: 300px" ref="chartRef"></a-card>
			</template>
		</a-spin>
	</a-card>
</template>

<style scoped></style>
