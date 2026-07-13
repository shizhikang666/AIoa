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
			<a-card>
				<a-space direction="vertical">
					<a-typography-title :level="5">开支数据统计</a-typography-title>
					<a-date-picker v-model:value="selectYear" picker="year" />
					<a-segmented v-model:value="activeDateIndex" :options="dateOptions">
						<template #label="{ title }">
							<div style="padding: 4px 4px">
								<div>{{ title }}</div>
							</div>
						</template>
					</a-segmented>
				</a-space>

				<a-row justify="space-between">
					<a-col :xs="11" :sm="11" :md="11" :lg="11" :xl="12">
						<the-expenditure-statistics
							:orgId="activeOrgId"
							:end-create-time="dateOptions[activeDateIndex].endCreateTime"
							:start-create-time="dateOptions[activeDateIndex].startCreateTime"
						/>
					</a-col>
					<a-col :xs="11" :sm="11" :md="11" :lg="11" :xl="11">
						<the-income-statistics
							:orgId="activeOrgId"
							:end-create-time="dateOptions[activeDateIndex].endCreateTime"
							:start-create-time="dateOptions[activeDateIndex].startCreateTime"
						/>
					</a-col>
				</a-row>
			</a-card>
		</a-col>
	</a-row>
</template>

<script setup name="bizDataReportSettlement">
	import { Empty } from 'ant-design-vue'
	import { isEmpty } from 'lodash-es'
	import { useOrg } from '@/composables/useOrg'
	import { useLoading } from '@/composables/useLoading'

	const { treeFieldNames, treeData, treeDefaultExpandedKeys, loadingTreeData } = useOrg()
	const { load, loading: cardLoading } = useLoading(loadingTreeData)
	import dayjs from '@/utils/dayjs'
	import TheExpenditureStatistics from '@/views/biz/bizdatareport/components/expenditureStatistics/theExpenditureStatistics.vue'
	import TheIncomeStatistics from '@/views/biz/bizdatareport/components/incomeStatistics/theIncomeStatistics.vue'

	const selectYear = ref(dayjs())
	const activeOrgId = ref('')
	const activeDateIndex = ref(0)
	const dateOptions = ref([])
	const treeSelect = async (item) => {
		activeOrgId.value = item[0]
	}
	const initDateOptions = () => {
		const currentYear = selectYear.value.year()
		dateOptions.value = []
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
	load()
	watchEffect(() => {
		initDateOptions()
	})
</script>

<style scoped></style>
