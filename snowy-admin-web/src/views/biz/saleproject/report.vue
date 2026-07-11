<script setup name="report">
	import SaleprojectStatistics from '@/views/biz/bizdatareport/components/chart/saleprojectStatistics.vue'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import { useLoading } from '@/composables/useLoading'
	import dayjs from '@/utils/dayjs'
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import { Decimal } from 'decimal.js'
	import { useOrg } from '@/composables/useOrg'
	import { useRouter } from 'vue-router'

	const router = useRouter()
	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()

	const createDefaultSearch = () => ({
		statisticsMonth: dayjs()
	})
	const searchFormState = ref(createDefaultSearch())
	const appliedSearchState = ref(createDefaultSearch())
	const saleProjectList = ref([])

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
	const rebateProjectCount = computed(() => {
		return saleProjectList.value.filter((project) => new Decimal(project.rebateAmount || 0).greaterThan(0)).length
	})

	const openRebateProjects = () => {
		const selectedRange = getMonthRange(selectedMonth.value)
		const query = {
			startCompletionTime: selectedRange.startCreateTime,
			endCompletionTime: selectedRange.endCreateTime,
			kickback: 'true'
		}
		if (appliedSearchState.value.orgId) {
			query.orgId = appliedSearchState.value.orgId
		}
		if (appliedSearchState.value.headName) {
			query.user = appliedSearchState.value.headName
		}

		router.push({
			path: '/biz/saleproject/dealProjectList',
			query
		})
	}

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
</template>

<style scoped>
	.rebate-statistic-card {
		cursor: pointer;
	}
</style>
