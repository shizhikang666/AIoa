<template>
	<a-card>
		<a-card title="汇总统计表">
			<a-date-picker v-model:value="selectYear" picker="year" />
			<br /><br />
			<a-flex v-if="loading" align="center" justify="center">
				<a-spin></a-spin>
			</a-flex>
			<template v-else>
				<div>
					<a-row justify="space-between">
						<a-col :xs="24" :sm="24" :md="24" :lg="24" :xl="24" :xxl="20">
							<template v-for="(item, i) in tableData">
								<a-table size="small" bordered :pagination="false" :data-source="item" :columns="columns">
									<template #headerCell="{ column }">
										<template v-if="column.key === 'org'">
											<span> {{ i + 1 }}月 </span>
										</template>
									</template>
									<template #bodyCell="{ column, record, text }">
										<template v-if="column.dataIndex === 'totalAmount'">
											<a-typography-link @click="gotoProjectList(record)">{{ record.actualAmount }} </a-typography-link>
										</template>
										<template v-if="column.dataIndex === 'otherTotalAmount'">
											<a-typography-link @click="gotoOtherAmountList(record)">{{ text }} </a-typography-link>
										</template>
										<template v-if="column.dataIndex === 'unPaidPaymentAmount'">
											<a-typography-link @click="openUmPayProjectList(record)">{{ text }} </a-typography-link>
										</template>
										<template v-if="column.dataIndex === 'totalUnpaidAmount'">
											<a-typography-link @click="openTotalUnPaidAmount(record)">{{ text }} </a-typography-link>
										</template>
										<template v-if="column.dataIndex === 'recoveredUnpaidAmounts'">
											<a-typography-link @click="openUnPayProjectList(record)">{{ text }} </a-typography-link>
										</template>
										<template v-if="column.dataIndex === 'balance'">
											<a-typography-link @click="openAccountList(record)">{{ text }} </a-typography-link>
										</template>

										<template v-if="column.dataIndex === 'totalExpenditure'">
											<a-typography-link @click="openExpenditureListRef(record)">{{ text }} </a-typography-link>
										</template>
									</template>
								</a-table>
								<br />
							</template>
							<br />
							<a-table size="small" bordered :data-source="allUnPayProject" :columns="unPayProjectColumns">
								<template #title>
									<div class="title" :style="{ backgroundColor: token.colorFillQuaternary }">
										<a-typography-title class="text-center" :level="5">未回款统计表 </a-typography-title>
									</div>
								</template>
							</a-table>
							<br />
							<br />
							<a-table size="small" bordered :data-source="allUnPayLoad" :columns="unPayLoanColumns">
								<template #title>
									<div class="title" :style="{ backgroundColor: token.colorFillQuaternary }">
										<a-typography-title class="text-center" :level="5">（借款/代付/保证金）统计表 </a-typography-title>
									</div>
								</template>
							</a-table>
						</a-col>
					</a-row>
				</div>
			</template>
		</a-card>
	</a-card>
	<theUnPayProjectList ref="theUnPayProjectListRef"></theUnPayProjectList>
	<the-old-pay-project-list ref="theOldPayProjectListRef"></the-old-pay-project-list>
	<TheAccountList ref="theAccountListRef"></TheAccountList>
	<theExpenditureList ref="theExpenditureListRef" />
	<the-total-un-paid-amount ref="theTotalUnPaidAmountRef"></the-total-un-paid-amount>
</template>

<style lang="less" scoped>
	::v-deep(.ant-table-title) {
		padding: 0 !important;

		.title {
			padding: 8px;
		}

		//colorFillTertiary
		//background-color: @color-fill-tertiary;

		//colorFillQuaternary
		//background-color: @colorFillQuaternary;
		//
	}
</style>

<script setup lang="js" name="summaryStatistics">
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import { useLoading } from '@/composables/useLoading'
	import dayjs from '@/utils/dayjs'
	import { theme } from 'ant-design-vue'
	import { useRouter } from 'vue-router'
	import TheUnPayProjectList from '@/views/biz/bizdatareport/components/summaryStatistics/theUnPayProjectList.vue'
	import { useTemplateRef } from 'vue'
	import TheOldPayProjectList from '@/views/biz/bizdatareport/components/summaryStatistics/theOldPayProjectList.vue'
	import TheAccountList from '@/views/biz/bizdatareport/components/summaryStatistics/theAccountList.vue'
	import TheExpenditureList from '@/views/biz/bizdatareport/components/summaryStatistics/theExpenditureList.vue'
	import TheTotalUnPaidAmount from '@/views/biz/bizdatareport/components/summaryStatistics/theTotalUnPaidAmount.vue'
	import { Decimal } from 'decimal.js'

	const theOldPayProjectListRef = useTemplateRef('theOldPayProjectListRef')
	const theUnPayProjectListRef = useTemplateRef('theUnPayProjectListRef')
	const theTotalUnPaidAmountRef = useTemplateRef('theTotalUnPaidAmountRef')
	const theAccountListRef = useTemplateRef('theAccountListRef')
	const theExpenditureListRef = useTemplateRef('theExpenditureListRef')
	const router = useRouter()
	const { useToken } = theme
	const { token } = useToken()
	const calc = (result, year) => {
		const worker = new Worker(new URL('./components/webWork/calcStatisics.js', import.meta.url), {
			type: 'module'
		})
		worker.postMessage({ result, year })
		return new Promise((resolve, reject) => {
			worker.onmessage = function (event) {
				const receivedData = event.data
				resolve(receivedData)
			}
			worker.onerror = function (event) {
				reject(event)
			}
		})
	}
	const gotoProjectList = (record) => {
		const startCreateTime = dayjs(record.time).startOf('month').format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = dayjs(record.time).endOf('month').format('YYYY-MM-DD HH:mm:ss')
		router.push({
			path: '/biz/saleproject/dealProjectList',
			query: {
				startCompletionTime: startCreateTime,
				endCompletionTime: endCreateTime,
				projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED',
				orgId: record.orgId
			}
		})
	}

	const gotoOtherAmountList = (record) => {
		const startCreateTime = dayjs(record.time).startOf('month').format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = dayjs(record.time).endOf('month').format('YYYY-MM-DD HH:mm:ss')
		router.push({
			path: '/biz/paymentrecord',
			query: {
				startPayerTime: startCreateTime,
				endPayerTime: endCreateTime,
				settlementCategory: 'other,INTEREST_INCOME',
				orgId: record.orgId
			}
		})
	}

	const openUmPayProjectList = (record) => {
		theUnPayProjectListRef.value.onOpen(record)
	}

	const openTotalUnPaidAmount = (record) => {
		theTotalUnPaidAmountRef.value.onOpen(record)
	}

	const openUnPayProjectList = (record) => {
		theOldPayProjectListRef.value.onOpen(record)
	}

	const openAccountList = (record) => {
		theAccountListRef.value.onOpen(record)
	}
	const openExpenditureListRef = (record) => {
		theExpenditureListRef.value.onOpen(record)
	}

	const tableData = ref([])
	const selectYear = ref(dayjs())
	const allUnPayLoad = ref([])
	const allUnPayProject = ref([])
	const columns = [
		{
			title: '所属公司',
			dataIndex: 'org',
			key: 'org'
		},
		{
			title: '业绩总额',
			dataIndex: 'totalAmount',
			width: 120
		},
		{
			title: '其他营收总额',
			dataIndex: 'otherTotalAmount',
			width: 120
		},
		{
			title: '新增未回款',
			dataIndex: 'unPaidPaymentAmount',
			width: 120
		},
		{
			title: '收回未回款',
			dataIndex: 'recoveredUnpaidAmounts',
			width: 120
		},
		{
			title: '未回款总计',
			dataIndex: 'totalUnpaidAmount'
		},
		{
			title: '借款/代付/保证金',
			dataIndex: 'loan',
			width: 200
		},
		{
			title: '开支总计',
			dataIndex: 'totalExpenditure'
		},
		{
			title: '账户余额总计',
			dataIndex: 'balance'
		}
	]
	const unPayProjectColumns = ref([
		{
			title: '所属公司',
			dataIndex: 'company',
			filters: [],
			onFilter: (value, record) => record.company.indexOf(value) === 0
		},
		{
			title: '业务员',
			dataIndex: 'headName',
			onFilter: (value, record) => record.headName.indexOf(value) === 0
		},
		{
			title: '客户名称',
			dataIndex: 'customerName',
			onFilter: (value, record) => record.customerName.indexOf(value) === 0
		},
		{
			title: '欠款金额',
			dataIndex: 'unPayAmount',
			key: 'unPayAmount',
			sorter: {
				compare: (a, b) => a.unPayAmount - b.unPayAmount,
				multiple: 1
			}
		},
		{
			title: '所属年度',
			dataIndex: 'year',
			sorter: {
				compare: (a, b) => a.year - b.year,
				multiple: 3
			}
		},
		{
			title: '所属月份',
			dataIndex: 'month',
			sorter: {
				compare: (a, b) => a.month - b.month,
				multiple: 2
			}
		},
		{
			title: '备注',
			dataIndex: 'remark',
			width: 300
		}
	])
	const unPayLoanColumns = ref([
		{
			title: '所属公司',
			dataIndex: 'company',
			filters: [],
			onFilter: (value, record) => record.company.indexOf(value) === 0
		},
		{
			title: '欠款金额',
			dataIndex: 'amount',
			width: 120,
			sorter: {
				compare: (a, b) => a.amount - b.amount,
				multiple: 1
			}
		},
		{
			title: '借款事由',
			dataIndex: 'remark'
		},
		{
			title: '所属年度',
			dataIndex: 'year',
			width: 120,
			sorter: {
				compare: (a, b) => a.year - b.year,
				multiple: 3
			}
		},
		{
			title: '所属月份',
			dataIndex: 'month',
			width: 120,
			sorter: {
				compare: (a, b) => a.month - b.month,
				multiple: 2
			}
		}
	])
	const { load, loading, error } = useLoading(async () => {
		const year = selectYear.value.format('YYYY-MM-DD HH:mm:ss')
		const result = await bizDataReportApi.summaryStatistics({
			year
		})
		const now = dayjs()
		const month = []
		for (let i = 0; i < 12; i++) {
			month[i] = []
		}
		allUnPayLoad.value = []
		allUnPayProject.value = []
		for (let i = 0; i < result.length; i++) {
			const res = await calc(result[i], year)
			allUnPayLoad.value.push(...res.unPayLoan)
			allUnPayProject.value.push(...res.unPayProject)
			res.month.forEach((item, index) => {
				month[index].push({
					time: dayjs(year).month(index).startOf('month').format('YYYY-MM-DD'),
					org: res.org.name,
					orgId: res.org.id,
					...item
				})
			})
		}

		unPayLoanColumns.value[0].filters = [...new Set(allUnPayLoad.value.map((item) => item.company))].map((v) => {
			return {
				text: v,
				value: v
			}
		})

		unPayProjectColumns.value[0].filters = [...new Set(allUnPayProject.value.map((item) => item.company))].map((v) => {
			return {
				text: v,
				value: v
			}
		})

		unPayProjectColumns.value[1].filters = [...new Set(allUnPayProject.value.map((item) => item.headName))].map((v) => {
			return {
				text: v,
				value: v
			}
		})

		unPayProjectColumns.value[2].filters = [...new Set(allUnPayProject.value.map((item) => item.customerName))].map(
			(v) => {
				return {
					text: v,
					value: v
				}
			}
		)

		tableData.value = month.filter((item, index) => {
			item.forEach((v) => {
				v.actualAmount = new Decimal(v.totalAmount).sub(v.totalRebateAmount).toString()
			})

			return dayjs(year).month(index).startOf('month').isBefore(now)
		})
	})

	watchEffect(() => {
		load()
	})
</script>
