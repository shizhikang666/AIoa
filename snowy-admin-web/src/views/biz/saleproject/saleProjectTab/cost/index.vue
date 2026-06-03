<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<a-row :gutter="16">
			<a-col :span="6">
				<a-card>
					<a-statistic :precision="2" :value="salesRevenue" style="margin-right: 50px">
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
					<a-statistic :precision="2" :value="cost" style="margin-right: 50px">
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
					<a-statistic :precision="2" :value="grossProfit" style="margin-right: 50px">
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
					<a-statistic :precision="2" suffix="%" :value="grossProfitLv" style="margin-right: 50px">
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
		<a-table row-key="id" size="small" :columns="columns" :data-source="list">
			<template #bodyCell="{ column, record }"></template>
		</a-table>
	</a-skeleton>
	<a-result v-else status="500" title="500" sub-title="服务器错误">
		<template #extra>
			<a-button type="primary" @click="load">重新加载</a-button>
		</template>
	</a-result>
</template>

<script name="bizSaleProjectCost" setup lang="js">
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { useLoading } from '@/composables/useLoading'
	import { Decimal } from 'decimal.js'

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			key: 'productName'
		},
		{
			title: '实际数量',
			dataIndex: 'amount'
		},
		{
			title: '平均采购单价',
			dataIndex: 'avgUnitAmount',
			key: 'avgUnitAmount'
		},
		{
			title: '总成本',
			dataIndex: 'countAmount',
			key: 'countAmount'
		}
	]
	const list = ref([])
	const { projectId, projectInfo } = defineProps({
		projectId: String,
		projectInfo: Object
	})
	const { load, loading, error } = useLoading(async () => {
		const res = await bizSaleProjectApi.costBizSaleProjectDetails({ id: projectId })
		const { items, productItems, returnOrders } = res
		// TODO: 处理退货订单，目前未处理套件中单产品退货
		returnOrders.forEach((order) => {
			order.productList.forEach((product) => {
				const find = productItems.find((productItem) => {
					return productItem.id === product.projectProductItemId
				})
				if (find) {
					find.number = find.number - product.amount
				}
			})
		})
		let resultProduct = productItems.filter((item) => {
			return item.number > 0
		})

		const keyMap = {}
		items.forEach((item) => {
			keyMap[item.productId] = item
		})

		resultProduct.forEach((product) => {
			product.amount = product.number
			if (product.children && product.children.length > 0) {
				let baseCountAmount = new Decimal(0)
				product.children.forEach((child) => {
					const { product } = JSON.parse(child.extJson)
					child.productName = product.productName
					child.amount = child.number
					// child.avgUnitAmount = keyMap[product.id] ? keyMap[product.id].avgUnitAmount : 0

					const extjson = JSON.parse(child.extJson)

					child.avgUnitAmount = extjson?.product?.purchasePrice ? extjson?.product?.purchasePrice : 0

					child.countAmount = new Decimal(child.amount).mul(child.avgUnitAmount).toString()
					baseCountAmount = baseCountAmount.add(child.countAmount)
				})
				product.avgUnitAmount = baseCountAmount.toNumber()
			} else {
				// const find = keyMap[product.productId]
				// product.avgUnitAmount = find ? find.avgUnitAmount : 0
				product.avgUnitAmount = product.productPurchasePrice ? product.productPurchasePrice : 0
			}
			product.countAmount = new Decimal(product.avgUnitAmount).mul(product.amount).toNumber()
		})

		list.value = resultProduct
	})
	watchEffect(async () => {
		await load()
	})
	//采购成本
	const cost = computed(() => {
		return list.value.reduce((per, next) => {
			return per.add(new Decimal(next.amount).mul(next.avgUnitAmount))
		}, new Decimal(0))
	})

	//总销售额
	const salesRevenue = computed(() => {
		return new Decimal(projectInfo.totalPrice)
			.sub(new Decimal(projectInfo.rebateAmount ? projectInfo.rebateAmount : 0))
			.toString()
	})

	// 销售收入
	const grossProfit = computed(() => {
		const grossProfit = new Decimal(salesRevenue.value).minus(new Decimal(cost.value))

		return grossProfit.toString()
	})

	const grossProfitLv = computed(() => {
		const revenue = new Decimal(salesRevenue.value || 0)
		if (revenue.isZero()) {
			return 0
		}

		return new Decimal(grossProfit.value).dividedBy(revenue).times(100).toDecimalPlaces(2)
	})
</script>

<style scoped></style>
