<script setup name="report">
	import SaleprojectStatistics from '@/views/biz/bizdatareport/components/chart/saleprojectStatistics.vue'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import { useLoading } from '@/composables/useLoading'
	import dayjs from '@/utils/dayjs'
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import { Decimal } from 'decimal.js'
	import { useOrg } from '@/composables/useOrg'
	import Detail from './detail.vue'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()

	const createDefaultSearch = () => ({
		statisticsMonth: dayjs()
	})
	const searchFormState = ref(createDefaultSearch())
	const appliedSearchState = ref(createDefaultSearch())
	const saleProjectList = ref([])
	const rebateProjectModalOpen = ref(false)
	const projectDetailRef = ref()

	const selectedMonth = computed(() => appliedSearchState.value.statisticsMonth || dayjs())
	const selectedMonthLabel = computed(() => selectedMonth.value.format('YYYY年MM月'))
	const getMonthRange = (month) => ({
		startCreateTime: month.startOf('month').format('YYYY-MM-DD HH:mm:ss'),
		endCreateTime: month.endOf('month').format('YYYY-MM-DD HH:mm:ss')
	})

	const reset = async () => {
		searchFormState.value = createDefaultSearch()
		await load()
	}

	const { load, loading, error } = useLoading(async () => {
		const requestedMonth = searchFormState.value.statisticsMonth || dayjs()
		const param = {
			...getMonthRange(requestedMonth)
		}
		if (searchFormState.value.orgId) {
			param.orgId = searchFormState.value.orgId
		}
		if (searchFormState.value.headName) {
			param.headName = searchFormState.value.headName
		}

		const projects = await bizDataReportApi.bizSaleProjectDataList(param)
		saleProjectList.value = projects
		appliedSearchState.value = {
			...searchFormState.value,
			statisticsMonth: requestedMonth
		}
	})

	const totalAmount = computed(() => {
		return saleProjectList.value
			.reduce((total, project) => total.add(project.totalPrice || 0), new Decimal(0))
			.toString()
	})
	const amountCollected = computed(() => {
		return saleProjectList.value
			.reduce((total, project) => total.add(project.amountCollected || 0), new Decimal(0))
			.toString()
	})
	const rebateProjectList = computed(() => {
		return saleProjectList.value.filter((project) => new Decimal(project.rebateAmount || 0).greaterThan(0))
	})
	const rebateProjectCount = computed(() => rebateProjectList.value.length)
	const rebateAmountTotal = computed(() => {
		return rebateProjectList.value
			.reduce((total, project) => total.add(project.rebateAmount || 0), new Decimal(0))
			.toFixed(2)
	})
	const formatMoney = (value) => new Decimal(value || 0).toFixed(2)
	const actualDealAmount = (project) => {
		return new Decimal(project.totalPrice || 0).sub(project.rebateAmount || 0).toFixed(2)
	}
	const rebateProjectColumns = [
		{ title: '项目名称', dataIndex: 'projectName', width: 200 },
		{ title: '项目状态', dataIndex: 'projectState', width: 100 },
		{ title: '合同金额', dataIndex: 'initPrice', align: 'right', width: 110 },
		{ title: '回扣金额', dataIndex: 'rebateAmount', align: 'right', width: 110 },
		{ title: '实际成交金额', dataIndex: 'actualDealAmount', align: 'right', width: 130 },
		{ title: '累计收款金额', dataIndex: 'amountCollected', align: 'right', width: 130 },
		{ title: '负责人', dataIndex: 'headName', width: 110 },
		{ title: '所属组织', dataIndex: 'orgName', width: 120 },
		{ title: '成交时间', dataIndex: 'completionDate', width: 170 },
		{ title: '操作', dataIndex: 'action', align: 'center', fixed: 'right', width: 90 }
	]
	const rebateProjectPagination = {
		pageSize: 10,
		showSizeChanger: true,
		showTotal: (total) => `共 ${total} 个项目`
	}

	const openRebateProjects = () => {
		rebateProjectModalOpen.value = true
	}
	const openProjectDetail = (record) => projectDetailRef.value?.onOpen(record)

	onMounted(load)
</script>

<template>
	<a-col :xs="24" :sm="24" :md="24" :lg="19" :xl="19">
		<a-card>
			<a-form name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
				<a-row :gutter="16">
					<a-col :xs="24" :sm="12" :lg="6">
						<a-form-item label="统计年月" name="statisticsMonth">
							<a-date-picker
								v-model:value="searchFormState.statisticsMonth"
								picker="month"
								format="YYYY年MM月"
								placeholder="请选择统计年月"
								:allow-clear="false"
								class="xn-wd"
							/>
						</a-form-item>
					</a-col>
					<a-col :xs="24" :sm="12" :lg="6">
						<a-form-item label="负责人" name="headName">
							<a-input v-model:value="searchFormState.headName" placeholder="请输入负责人名称" allow-clear />
						</a-form-item>
					</a-col>
					<a-col :xs="24" :sm="12" :lg="6">
						<a-form-item label="所属组织" name="orgId">
							<a-tree-select
								v-model:value="searchFormState.orgId"
								class="xn-wd"
								:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
								placeholder="请选择组织"
								allow-clear
								:tree-data="treeData"
								:field-names="{ children: 'children', label: 'name', value: 'id' }"
								selectable="false"
								tree-line
							/>
						</a-form-item>
					</a-col>
					<a-col :xs="24" :sm="12" :lg="6">
						<a-form-item>
							<a-space>
								<a-button type="primary" :loading="loading" @click="load">查询</a-button>
								<a-button @click="reset">重置</a-button>
							</a-space>
						</a-form-item>
					</a-col>
				</a-row>
			</a-form>

			<error-result v-if="error" @reload="load" />
			<a-spin v-else :spinning="loading">
				<a-row :gutter="16">
					<a-col :xs="24" :md="8">
						<a-card size="small" :title="`${selectedMonthLabel}销售额`">
							<a-statistic :precision="2" :value="totalAmount" />
						</a-card>
					</a-col>
					<a-col :xs="24" :md="8">
						<a-card size="small" :title="`${selectedMonthLabel}已回款销售额`">
							<a-statistic :precision="2" :value="amountCollected" />
						</a-card>
					</a-col>
					<a-col :xs="24" :md="8">
						<a-card
							size="small"
							:title="`${selectedMonthLabel}回扣项目数`"
							hoverable
							class="rebate-statistic-card"
							@click="openRebateProjects"
						>
							<a-statistic :precision="0" :value="rebateProjectCount" suffix="个" />
						</a-card>
					</a-col>
				</a-row>
			</a-spin>
		</a-card>

		<br />

		<saleproject-statistics
			:key="`${selectedMonth.format('YYYY')}-${appliedSearchState.orgId || ''}-${appliedSearchState.headName || ''}`"
			:year="selectedMonth"
			:head-name="appliedSearchState.headName"
			:org-id="appliedSearchState.orgId"
		/>
	</a-col>
	<a-modal
		v-model:open="rebateProjectModalOpen"
		:title="`${selectedMonthLabel}回扣项目明细`"
		width="min(1200px, calc(100vw - 32px))"
		:footer="null"
		:z-index="900"
		destroy-on-close
	>
		<div class="rebate-project-summary">
			<span>项目数：{{ rebateProjectCount }} 个</span>
			<span>回扣合计：￥{{ rebateAmountTotal }}</span>
		</div>
		<a-table
			:data-source="rebateProjectList"
			:columns="rebateProjectColumns"
			:pagination="rebateProjectPagination"
			:scroll="{ x: 1270 }"
			row-key="id"
			size="middle"
			bordered
		>
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'projectName'">
					<a @click="openProjectDetail(record)">{{ record.projectName }}</a>
				</template>
				<template v-else-if="column.dataIndex === 'projectState'">
					<a-tag :color="$TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE_COLOR', record.projectState)">
						{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE', record.projectState) }}
					</a-tag>
				</template>
				<template v-else-if="column.dataIndex === 'initPrice'"> ￥{{ formatMoney(record.initPrice) }} </template>
				<template v-else-if="column.dataIndex === 'rebateAmount'">
					<a-typography-text type="danger">￥{{ formatMoney(record.rebateAmount) }}</a-typography-text>
				</template>
				<template v-else-if="column.dataIndex === 'actualDealAmount'">
					￥{{ actualDealAmount(record) }}
				</template>
				<template v-else-if="column.dataIndex === 'amountCollected'">
					￥{{ formatMoney(record.amountCollected) }}
				</template>
				<template v-else-if="column.dataIndex === 'action'">
					<a @click="openProjectDetail(record)">查看详情</a>
				</template>
			</template>
		</a-table>
	</a-modal>
	<Detail ref="projectDetailRef" />
</template>

<style scoped>
	.rebate-statistic-card {
		cursor: pointer;
	}

	.rebate-project-summary {
		display: flex;
		gap: 24px;
		margin-bottom: 16px;
		font-weight: 600;
	}
</style>
