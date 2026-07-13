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
					<a-typography-title :level="5">销售利润成本及利润预估</a-typography-title>
					<a-segmented v-model:value="activeDateIndex" :options="dateOptions">
						<template #label="{ title }">
							<div style="padding: 4px 4px">
								<div>{{ title }}</div>
							</div>
						</template>
					</a-segmented>

					<a-skeleton v-if="!error" active :loading="loadingResult">
						<a-row :gutter="16">
							<a-col :span="6">
								<a-card>
									<a-statistic :precision="2" :value="allInfo.salesRevenue">
										<template #title>
											<span>销售额</span>
											<a-tooltip placement="right">
												<template #title>
													<span>总金额扣除客户回扣</span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card>
									<a-statistic :precision="2" :value="allInfo.cost">
										<template #title>
											<span>采购成本</span>
											<a-tooltip placement="right">
												<template #title>
													<span>产品采购单平均采购单价 * 产品实际出库数量</span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card>
									<a-statistic :precision="2" :value="allInfo.grossProfit">
										<template #title>
											<span>毛利</span>
											<a-tooltip placement="right">
												<template #title>
													<span>毛利 = 销售收入 - 销售成本 - 客户回扣 </span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
							<a-col :span="6">
								<a-card>
									<a-statistic :precision="2" suffix="%" :value="allInfo.grossProfitLv">
										<template #title>
											<span>毛利率</span>
											<a-tooltip placement="right">
												<template #title>
													<br />
													<span>毛利率：(销售收入 - 销售成本)/毛利 * 100% </span>
												</template>
												<question-circle-two-tone style="margin-left: 5px" />
											</a-tooltip>
										</template>
									</a-statistic>
								</a-card>
							</a-col>
						</a-row>
						<br />
						<a-table size="small" :data-source="productListData" :columns="columns">
							<template #headerCell="{ column }">
								<template v-if="column.key === 'name'">
									<span style="color: #1890ff">产品名称</span>
								</template>
							</template>
							<template #customFilterDropdown="{ setSelectedKeys, selectedKeys, confirm, clearFilters, column }">
								<div style="padding: 8px">
									<a-input
										ref="searchInput"
										:placeholder="`搜索产品名字`"
										:value="selectedKeys[0]"
										style="width: 188px; margin-bottom: 8px; display: block"
										@change="(e) => setSelectedKeys(e.target.value ? [e.target.value] : [])"
										@pressEnter="handleSearch(selectedKeys, confirm, column.dataIndex)"
									/>
									<a-button
										type="primary"
										size="small"
										style="width: 90px; margin-right: 8px"
										@click="handleSearch(selectedKeys, confirm, column.dataIndex)"
									>
										<template #icon>
											<SearchOutlined />
										</template>
										搜索
									</a-button>
									<a-button size="small" style="width: 90px" @click="handleReset(clearFilters)"> 重置 </a-button>
								</div>
							</template>
							<template #customFilterIcon="{ filtered }">
								<search-outlined :style="{ color: filtered ? '#108ee9' : undefined }" />
							</template>
							<template #bodyCell="{ text, column }">
								<span v-if="state.searchText && state.searchedColumn === column.dataIndex">
									<template
										v-for="(fragment, i) in text
											.toString()
											.split(new RegExp(`(?<=${state.searchText})|(?=${state.searchText})`, 'i'))"
									>
										<mark v-if="fragment.toLowerCase() === state.searchText.toLowerCase()" :key="i" class="highlight">
											{{ fragment }}
										</mark>
										<template v-else>{{ fragment }}</template>
									</template>
								</span>
							</template>
						</a-table>
					</a-skeleton>
				</a-space>
			</a-card>
		</a-col>
	</a-row>
</template>

<script setup name="saleProfit">
	import { Empty } from 'ant-design-vue'
	import { useOrg } from '@/composables/useOrg'
	import { useLoading } from '@/composables/useLoading'
	import dayjs from '@/utils/dayjs'
	import bizDataReportApi from '@/api/biz/bizDataReportApi'
	import { runWebWorker } from '@/utils/webWork'

	const { treeFieldNames, treeData, treeDefaultExpandedKeys, loadingTreeData } = useOrg()
	const { load, loading: cardLoading } = useLoading(loadingTreeData)
	const activeOrgId = ref('')
	load()
	const activeDateIndex = ref(0)
	const dateOptions = ref([])
	const treeSelect = async (item) => {
		activeOrgId.value = item[0]
	}
	const initDateOptions = () => {
		const currentYear = dayjs().year()
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
	const allInfo = ref({
		cost: 0,
		salesRevenue: 0,
		grossProfitLv: 0,
		grossProfit: 0
	})
	const productListData = ref([])
	initDateOptions()
	const {
		loading: loadingResult,
		load: loadResult,
		error
	} = useLoading(async (param) => {
		const res = await bizDataReportApi.saleProfit(param)
		const work = new Worker(new URL('./webWork/calcProfit.js', import.meta.url), {
			type: 'module'
		})
		const { cost, salesRevenue, grossProfitLv, grossProfit, productList } = await runWebWorker(work, res)
		productListData.value = productList

		allInfo.value = {
			cost,
			salesRevenue,
			grossProfitLv: isNaN(grossProfitLv) ? 0 : grossProfitLv,
			grossProfit
		}
	})

	const state = reactive({
		searchText: '',
		searchedColumn: ''
	})
	const searchInput = ref()
	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			key: 'productName',
			customFilterDropdown: true,
			onFilter: (value, record) => record.productName.toString().toLowerCase().includes(value.toLowerCase()),
			onFilterDropdownOpenChange: (visible) => {
				if (visible) {
					setTimeout(() => {
						searchInput.value.focus()
					}, 100)
				}
			}
		},
		{
			title: '总出库数量',
			dataIndex: 'totalNumber',
			key: 'totalNumber',
			sorter: {
				compare: (a, b) => a.totalNumber - b.totalNumber,
				multiple: 1
			}
		},
		{
			title: '平均采购单价',
			dataIndex: 'unitPrice',
			key: 'unitPrice',
			sorter: {
				compare: (a, b) => a.unitPrice - b.unitPrice,
				multiple: 2
			}
		},
		{
			title: '总采购额',
			dataIndex: 'totalAmount',
			key: 'totalAmount',
			sorter: {
				compare: (a, b) => a.totalAmount - b.totalAmount,
				multiple: 3
			}
		}
	]
	const handleSearch = (selectedKeys, confirm, dataIndex) => {
		confirm()
		state.searchText = selectedKeys[0]
		state.searchedColumn = dataIndex
	}
	const handleReset = (clearFilters) => {
		clearFilters({
			confirm: true
		})
		state.searchText = ''
	}

	watchEffect(() => {
		const { startCreateTime, endCreateTime } = dateOptions.value[activeDateIndex.value]
		const orgId = activeOrgId.value

		loadResult({
			startCreateTime,
			endCreateTime,
			orgId
		})
	})
</script>

<style scoped></style>
