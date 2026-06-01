<template>
	<a-row :gutter="10">
		<a-col :xs="24" :sm="24" :md="24" :lg="6" :xl="6">
			<a-card :bordered="false" :loading="cardLoading">
				<a-tree
					v-if="treeData.length > 0"
					v-model:expandedKeys="treeDefaultExpandedKeys"
					:tree-data="treeData"
					:field-names="treeFieldNames"
					@select="treeSelect"
				>
				</a-tree>
				<a-empty v-else :image="Empty.PRESENTED_IMAGE_SIMPLE" />
			</a-card>
		</a-col>

		<a-col :xs="24" :sm="24" :md="24" :lg="18" :xl="18">
			<a-card title="销售金额统计">
				<a-row :gutter="10" :wrap="true">
					<a-col :key="i" v-for="(item, i) in list" class="gutter-row">
						<div class="gutter-box">
							<!--本月业绩营收-->
							<theMonthDataPreview
								:info="item.info"
								:hoverable="true"
								:org-id="orgId"
								:contrast="item.contrast"
								:title="item.title"
								:load-data="item.loadData"
								@click="item.onClick"
							></theMonthDataPreview>
						</div>
					</a-col>
				</a-row>
			</a-card>
			<br />
			<a-card>
				<a-space direction="vertical">
					<a-typography-title :level="5">全年数据统计</a-typography-title>
					<a-date-picker v-model:value="selectYear" picker="year" />
					<a-segmented v-model:value="activeDateIndex" :options="dateOptions">
						<template #label="{ title }">
							<div style="padding: 4px 4px">
								<div>{{ title }}</div>
							</div>
						</template>
					</a-segmented>
					<a-row :gutter="[10, 10]" :wrap="true">
						<error-result v-if="error" @reload="load"></error-result>
						<template v-else>
							<a-col :span="6">
								<a-card @click="gotoSaleProject" :hoverable="true">
									<a-skeleton :paragraph="{ rows: 1 }" active :loading="loading">
										<a-statistic :value="info.count">
											<template #title>
												<span>项目总数</span>
												<a-tooltip placement="right">
													<template #title>
														<span>时间范围内新建的项目总数</span>
													</template>
													<question-circle-two-tone style="margin-left: 5px" />
												</a-tooltip>
											</template>
										</a-statistic>
									</a-skeleton>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card
									@click="
										gotoDealSaleProject({
											projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED'
										})
									"
									:hoverable="true"
								>
									<a-skeleton :paragraph="{ rows: 1 }" active :loading="loading">
										<a-statistic :value="info.dealCount">
											<template #title>
												<span>成交项目总数</span>
												<a-tooltip placement="right">
													<template #title>
														<span>时间范围内成交的项目总数，包括以前再跟进中的项目</span>
													</template>
													<question-circle-two-tone style="margin-left: 5px" />
												</a-tooltip>
											</template>
										</a-statistic>
									</a-skeleton>
								</a-card>
							</a-col>

							<a-col :span="6">
								<a-card
									:hoverable="true"
									@click="
										gotoDealSaleProject({
											playState: 'PARTIALLY_PAID,PAID'
										})
									"
								>
									<a-statistic :precision="2" :value="totalAmountInfo.dealAmount">
										<template #title>
											<span>已收款</span>
											<a-tooltip placement="right">
												<template #title>
													<span>时间范围内成交项目的收款金额，会随收款记录添加变更</span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card
									:hoverable="true"
									@click="gotoDealSaleProject({ projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED' })"
								>
									<a-statistic :precision="2" :value="totalAmountInfo.countTotalAmount">
										<template #title>
											<span>合同金额</span>
											<a-tooltip placement="right">
												<template #title>
													<span>时间范围项目的成交额（含回扣），会减去退货金额，累加补货金额</span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card
									:hoverable="true"
									@click="gotoDealSaleProject({ projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED' })"
								>
									<a-statistic :precision="2" :value="totalAmountInfo.countTotalAmountRebate">
										<template #title>
											<span>实际成交额</span>
											<a-tooltip placement="right">
												<template #title>
													<span>时间范围项目的成交额（不含回扣），会减去退货金额和回扣，累加补货金额</span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card
									:hoverable="true"
									@click="
										gotoDealSaleProject({
											projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED',
											kickback: true
										})
									"
								>
									<a-statistic
										:value-style="{ color: '#3f8600' }"
										:precision="2"
										:value="totalAmountInfo.countRebateAmount"
									>
										<template #title>
											<span>回扣额</span>
											<a-tooltip placement="right">
												<template #title>
													<span>时间范围项目回扣额</span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card
									:hoverable="true"
									@click="gotoDealSaleProject({ projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED' })"
								>
									<a-statistic
										:value-style="{ color: '#3f8600' }"
										:precision="2"
										:value="-totalAmountInfo.countTotalReturnAmount"
									>
										<template #title>
											<span>退款额</span>
											<a-tooltip placement="right">
												<template #title>
													<span>由财务审核后从账户实际退款给客户的总金额</span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
						</template>
					</a-row>
				</a-space>
			</a-card>
			<br />
			<saleproject-statistics
				:year="selectYear"
				v-model:data-source="dataSource"
				:org-id="orgId"
			></saleproject-statistics>
		</a-col>
	</a-row>
</template>
<script setup name="bizDataReport">
	import theMonthDataPreview from '@/views/biz/bizdatareport/components/month/theMonthDataPreview.vue'
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import { useLoading } from '@/composables/useLoading'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import dayjs from '@/utils/dayjs'
	import { Decimal } from 'decimal.js'
	import { Empty } from 'ant-design-vue'
	import { useOrg } from '@/composables/useOrg'
	import SaleprojectStatistics from '@/views/biz/bizdatareport/components/chart/saleprojectStatistics.vue'
	import { useRouter } from 'vue-router'

	const activeDateIndex = ref(0)
	const dateOptions = ref([])
	const router = useRouter()
	const selectYear = ref(dayjs())
	const currentNowMonth = dayjs().month()
	activeDateIndex.value = currentNowMonth + 1

	const initDateOptions = () => {
		dateOptions.value = []
		const currentYear = selectYear.value.year()
		// 创建一个数组来存储每个月的开始和结束时间
		const months = []
		for (let month = 0; month < 12; month++) {
			const startOfMonth = dayjs().year(currentYear).month(month).startOf('month')
			const endOfMonth = dayjs().year(currentYear).month(month).endOf('month')
			const currenMonth = month + 1
			months.push({
				value: currenMonth,
				title: `${currenMonth}月`,
				// 月份从 1 开始
				startCreateTime: startOfMonth.format('YYYY-MM-DD HH:mm:ss'),
				endCreateTime: endOfMonth.format('YYYY-MM-DD HH:mm:ss')
			})
		}

		// 获取今年的开始时间
		const startOfYear = dayjs().year(currentYear).startOf('year')

		// 获取今年的结束时间
		const endOfYear = dayjs().year(currentYear).endOf('year')

		const startCreateTime = startOfYear.format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = endOfYear.format('YYYY-MM-DD HH:mm:ss')

		dateOptions.value.push({
			title: '全年',
			value: 0,
			startCreateTime,
			endCreateTime
		})
		dateOptions.value.push(...months)
	}
	const { treeFieldNames, treeData, treeDefaultExpandedKeys, loadingTreeData } = useOrg()
	const { load: loadDept, loading: cardLoading } = useLoading(loadingTreeData)
	const orgId = ref('')
	const treeSelect = async (item) => {
		orgId.value = item[0]
	}

	const now = dayjs()
	// 获取本月的第一天
	const firstDayOfMonth = now.startOf('month')
	// 获取本月的最后一天
	const lastDayOfMonth = now.endOf('month')
	// 设置时间为 00:00:00
	const startOfMonth = firstDayOfMonth.hour(0).minute(0).second(0)
	const gotoDealSaleProject = (query) => {
		const { startCreateTime, endCreateTime } = dateOptions.value[activeDateIndex.value]
		router.push({
			path: '/biz/saleproject/dealProjectList',
			query: {
				startCompletionTime: startCreateTime,
				endCompletionTime: endCreateTime,
				...query,
				orgId: orgId.value
			}
		})
	}
	const gotoSaleProject = (query) => {
		const { startCreateTime, endCreateTime } = dateOptions.value[activeDateIndex.value]
		router.push({
			path: '/biz/saleproject',
			query: {
				startCreateTime: startCreateTime,
				endCreateTime: endCreateTime,
				...query,
				orgId: orgId.value
			}
		})
	}

	// 设置时间为 23:59:59
	const endOfMonth = lastDayOfMonth.hour(23).minute(59).second(59)
	const startCreateTime = startOfMonth.format('YYYY-MM-DD HH:mm:ss')
	const endCreateTime = endOfMonth.format('YYYY-MM-DD HH:mm:ss')
	const list = ref([
		{
			title: '本月业绩营收',
			contrast: true,
			info: '本月成交项目的总金额，（包括之前创建的项目在本月成交的,创建单，补货单，退货单金额之和，未扣除客户回扣的总业绩）。',
			loadData: (param) => {
				return bizDataReportApi.bizSaleProjectDataReport(param)
			},
			onClick: () => {
				router.push({
					path: '/biz/saleproject/dealProjectList',
					query: {
						startCompletionTime: startCreateTime,
						endCompletionTime: endCreateTime,
						projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED',
						orgId: orgId.value
					}
				})
			}
		},
		{
			title: '本月累计回款',
			info: '本月累计回款，包括之前成交的项目，和提前收的款项（排除了收款金额不在当前月份的，比如说2月份成交的项目，在一月份已经把钱收了）',
			loadData: async (param) => {
				const res = await bizDataReportApi.bizSettlementAccountIncome({ ...param, category: 'PROJECT_PLAY' })
				let count = new Decimal(0)
				res.forEach((item) => {
					count = count.add(item.amount)
				})
				return {
					amount: count.toNumber()
				}
			},
			onClick: () => {
				router.push({
					path: '/biz/saleproject/dealProjectList',
					query: {
						startCompletionTime: startCreateTime,
						endCompletionTime: endCreateTime,
						playState: 'PARTIALLY_PAID,PAID',
						orgId: orgId.value
					}
				})
			}
		},
		{
			title: '本月新增未回款',
			info: '本月成交的项目未回款的金额总计',
			loadData: (param) => {
				return bizDataReportApi.bizSaleProjectDataReportUnpaidPayment(param)
			},
			onClick: () => {
				router.push({
					path: '/biz/saleproject/dealProjectList',

					query: {
						startCompletionTime: startCreateTime,
						endCompletionTime: endCreateTime,
						projectState: 'PARTIALLY_SHIPPED,WAIT_DELIVER,SHIPPED,COMPLETED',
						playState: 'UNPAID,PARTIALLY_PAID',
						orgId: orgId.value
					}
				})
			}
		}
	])
	const projectList = ref([])
	const { load, loading, error } = useLoading(async () => {
		const { startCreateTime, endCreateTime } = dateOptions.value[activeDateIndex.value]
		const result = await bizDataReportApi.bizSaleProjectDataReportList({
			startCreateTime,
			endCreateTime,
			orgId: orgId.value
		})
		projectList.value = result.list
	})
	const dealStatuses = ['WAIT_DELIVER', 'SHIPPED', 'PARTIALLY_SHIPPED', 'COMPLETED']
	const dealRegex = new RegExp(dealStatuses.join('|'))
	//成交项目数量
	const info = computed(() => {
		const { startCreateTime, endCreateTime } = dateOptions.value[activeDateIndex.value]
		const startDay = dayjs(startCreateTime)
		const endDay = dayjs(endCreateTime)
		let dealCount = 0
		let count = 0
		projectList.value.forEach((item) => {
			const createTimeDayjs = dayjs(item.createTime)
			const completionDateDayjs = dayjs(item.completionDate)

			if (createTimeDayjs.isAfter(startDay) && createTimeDayjs.isBefore(endDay)) {
				count++
			}

			if (
				dealRegex.test(item.projectState) &&
				completionDateDayjs.isAfter(startDay) &&
				completionDateDayjs.isBefore(endDay)
			) {
				dealCount++
			}
		})

		return {
			dealCount,
			count
		}
	})

	loadDept()

	const dataSource = ref([])
	const totalAmountInfo = computed(() => {
		let dealAmount = new Decimal(0)
		let countTotalAmount = new Decimal(0)
		let countTotalReturnAmount = new Decimal(0)
		let countRebateAmount = new Decimal(0)
		const { startCreateTime, endCreateTime } = dateOptions.value[activeDateIndex.value]
		// 定义时间范围的开始和结束时间
		const startTime = dayjs(startCreateTime)
		const endTime = dayjs(endCreateTime)

		dataSource.value.forEach((item) => {
			const targetTime = dayjs(item.completionDate)
			if (!(targetTime.isAfter(startTime) && targetTime.isBefore(endTime))) {
				return
			}

			countTotalAmount = countTotalAmount.add(item.totalPrice)
			countTotalReturnAmount = countTotalReturnAmount.add(item.totalReturnAmount)
			dealAmount = dealAmount.add(item.amountCollected)
			countRebateAmount = countRebateAmount.add(item.rebateAmount ? item.rebateAmount : 0)
		})

		return {
			dealAmount: dealAmount.toString(),
			countTotalAmount: countTotalAmount.toString(),
			countTotalAmountRebate: countTotalAmount.sub(countRebateAmount).toString(),
			countTotalReturnAmount: countTotalReturnAmount.toString(),
			countRebateAmount: countRebateAmount.toString()
		}
	})

	watchEffect(() => {
		initDateOptions()
		load()
	})
</script>
<style scoped></style>
