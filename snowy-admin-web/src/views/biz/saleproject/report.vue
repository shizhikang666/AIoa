<script setup name="report">
	import SaleprojectStatistics from '@/views/biz/bizdatareport/components/chart/saleprojectStatistics.vue'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import { useLoading } from '@/composables/useLoading'
	import dayjs from '@/utils/dayjs'
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import { Decimal } from 'decimal.js'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const searchFormState = ref({})
	const saleProjectList = ref([])

	const reset = async () => {
		searchFormRef.value.resetFields()
		await load()
	}
	const { load, loading, error } = useLoading(async () => {
		const now = dayjs()
		// 获取本月的第一天
		const firstDayOfMonth = now.startOf('month')
		// 获取本月的最后一天
		const lastDayOfMonth = now.endOf('month')
		// 设置时间为 00:00:00
		const startOfMonth = firstDayOfMonth.hour(0).minute(0).second(0)

		// 设置时间为 23:59:59
		const endOfMonth = lastDayOfMonth.hour(23).minute(59).second(59)
		const startCreateTime = startOfMonth.format('YYYY-MM-DD HH:mm:ss')
		const endCreateTime = endOfMonth.format('YYYY-MM-DD HH:mm:ss')
		const param = {
			endCreateTime,
			startCreateTime
		}
		if (searchFormState.value.orgId) {
			param.orgId = searchFormState.value.orgId
		}

		if (searchFormState.value.orgId) {
			param.headName = searchFormState.value.headName
		}

		saleProjectList.value = await bizDataReportApi.bizSaleProjectDataList({
			...param
		})
	})
	const totalAmount = computed(() => {
		let total = new Decimal(0)
		saleProjectList.value.forEach((v) => {
			total = total.add(v.totalPrice)
		})

		return total.toString()
	})
	const amountCollected = computed(() => {
		let total = new Decimal(0)
		saleProjectList.value.forEach((v) => {
			total = total.add(v.amountCollected)
		})

		return total.toString()
	})
	watchEffect(() => {
		load()
	})
</script>

<template>
	<a-col :xs="24" :sm="24" :md="24" :lg="19" :xl="19">
		<a-card>
			<a-form ref="searchFormRef" name="advanced_search" :model="searchFormState" class="ant-advanced-search-form">
				<a-row :gutter="24">
					<a-col :span="6">
						<a-form-item label="负责人" name="headName">
							<a-input v-model:value="searchFormState.headName" placeholder="请输入负责人名称" />
						</a-form-item>
					</a-col>

					<a-col :span="6">
						<a-form-item label="所属组织：" name="orgId">
							<a-tree-select
								v-model:value="searchFormState.orgId"
								class="xn-wd"
								:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
								placeholder="请选择组织"
								allow-clear
								:tree-data="treeData"
								:field-names="{
									children: 'children',
									label: 'name',
									value: 'id'
								}"
								selectable="false"
								tree-line
							></a-tree-select>
						</a-form-item>
					</a-col>
					<a-col :span="6">
						<a-button type="primary" @click="load">查询</a-button>
						<a-button style="margin: 0 8px" @click="reset">重置</a-button>
					</a-col>
				</a-row>
			</a-form>
			<a-row :gutter="10" :wrap="true">
				<a-col class="gutter-row">
					<div class="gutter-box">
						<!--本月业绩营收-->
						<a-card title="本月销售额">
							<a-statistic :precision="2" :value="totalAmount" style="margin-right: 50px"></a-statistic>
						</a-card>
					</div>
				</a-col>
				<a-col class="gutter-row">
					<div class="gutter-box">
						<!--本月业绩营收-->
						<a-card title="已回款销售额">
							<a-statistic :precision="2" :value="amountCollected" style="margin-right: 50px"></a-statistic>
						</a-card>
					</div>
				</a-col>
			</a-row>
		</a-card>

		<br />

		<saleproject-statistics
			:head-name="searchFormState.headName"
			:org-id="searchFormState.orgId"
		></saleproject-statistics>
	</a-col>
</template>

<style scoped></style>
