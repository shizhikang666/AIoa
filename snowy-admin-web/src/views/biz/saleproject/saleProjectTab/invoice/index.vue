<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<template v-if="deliveryPlans.length > 0">
			<a-alert
				message="发货安排"
				description="每个安排对应一张发货单；待发货安排不会提前扣减库存。"
				type="info"
				show-icon
				style="margin-bottom: 16px"
			/>
			<a-card
				v-for="(plan, planIndex) in deliveryPlans"
				:key="plan.id || planIndex"
				size="small"
				class="delivery-plan-card"
			>
				<template #title>
					<a-space>
						<span>发货安排 {{ plan.planNo || planIndex + 1 }}</span>
						<a-tag :color="planStatusColor(plan.status)">{{ planStatusText(plan.status) }}</a-tag>
					</a-space>
				</template>
				<template #extra>
					<a-button
						v-if="hasPerm('bizSaleProjectExportDeliveryNote')"
						type="link"
						:loading="exportingPlanId === String(plan.id)"
						@click="exportDeliveryPlan(plan)"
					>
						导出此发货单
					</a-button>
				</template>
				<a-descriptions bordered size="small">
					<a-descriptions-item label="收货单位">{{ plan.unit || '--' }}</a-descriptions-item>
					<a-descriptions-item label="收货人">{{ plan.consignee || '--' }}</a-descriptions-item>
					<a-descriptions-item label="联系电话">{{ plan.phone || '--' }}</a-descriptions-item>
					<a-descriptions-item label="收货地址" :span="3">{{ plan.address || '--' }}</a-descriptions-item>
					<a-descriptions-item label="运费支付方式">
						{{ $TOOL.dictTypeData('FREIGHT_CATEGORY', plan.freightCategory) || '--' }}
					</a-descriptions-item>
					<a-descriptions-item label="运费">
						{{ plan.freight === null || plan.freight === undefined || plan.freight === '' ? '待填写' : `¥${plan.freight}` }}
					</a-descriptions-item>
					<a-descriptions-item label="指定物流">
						{{
							plan.logisticsCategory
								? $TOOL.dictTypeData('LOGISTICS_CATEGORY', plan.logisticsCategory)
								: '实际发货时确定'
						}}
					</a-descriptions-item>
					<a-descriptions-item v-if="plan.invoiceId" label="关联发货单" :span="3">
						{{ plan.invoiceId }}
					</a-descriptions-item>
					<a-descriptions-item label="备注" :span="3">{{ plan.remark || '--' }}</a-descriptions-item>
				</a-descriptions>
				<a-table
					class="shipment-items"
					row-key="rowKey"
					size="small"
					bordered
					:pagination="false"
					:columns="deliveryPlanItemColumns"
					:data-source="deliveryPlanItems(plan)"
				/>
			</a-card>
			<a-divider orientation="left">实际发货记录</a-divider>
		</template>
		<template v-for="({ bizSaleProjectInvoice, invoiceItems }, i) in list" :key="i">
			<a-descriptions size="small" bordered>
				<template #title>
					<a-space>
						<span>发货信息</span>
						<a-tag v-if="invoicePlanNo(bizSaleProjectInvoice)" color="cyan">
							发货安排 {{ invoicePlanNo(bizSaleProjectInvoice) }}
						</a-tag>
						<a-tag :color="shipmentTypeColor(bizSaleProjectInvoice.shipmentType)">
							{{ shipmentTypeText(bizSaleProjectInvoice.shipmentType) }}
						</a-tag>
					</a-space>
				</template>
				<a-descriptions-item label="物流编号" :span="2">{{ bizSaleProjectInvoice.logisticsId }}</a-descriptions-item>
				<a-descriptions-item label="物流类型" :span="2">
					{{ $TOOL.dictTypeData('LOGISTICS_CATEGORY', bizSaleProjectInvoice.logisticsCategory) }}
				</a-descriptions-item>
				<a-descriptions-item label="收货人">{{ bizSaleProjectInvoice.consignee }}</a-descriptions-item>
				<a-descriptions-item label="收货地址">{{ bizSaleProjectInvoice.address }}</a-descriptions-item>
				<a-descriptions-item label="联系电话">{{ bizSaleProjectInvoice.phone }}</a-descriptions-item>
				<a-descriptions-item label="发货单位">{{ bizSaleProjectInvoice.unit }}</a-descriptions-item>
				<a-descriptions-item label="运费支付方式">
					{{ $TOOL.dictTypeData('FREIGHT_CATEGORY', bizSaleProjectInvoice.freightCategory) }}
				</a-descriptions-item>
				<a-descriptions-item label="发货时间">{{ bizSaleProjectInvoice.freightTime }}</a-descriptions-item>
				<a-descriptions-item label="流程编号">
					<a-typography-link @click="detailRef.onOpen({ instanceId: bizSaleProjectInvoice.processId })">
						{{ bizSaleProjectInvoice.processId }}
					</a-typography-link>
				</a-descriptions-item>
				<a-descriptions-item label="运费">¥{{ bizSaleProjectInvoice.freight }}</a-descriptions-item>
				<a-descriptions-item v-if="bizSaleProjectInvoice.hasReissueShipment" label="关联补发单" :span="3">
					<a-space direction="vertical" :size="4">
						<div v-for="order in bizSaleProjectInvoice.reissueOrders" :key="order.id">
							<a-tag color="orange">补发单（{{ order.createTime || order.id }}）</a-tag>
							<span v-if="order.createUserName">创建人：{{ order.createUserName }}</span>
							<span v-if="order.remark" class="reissue-remark">备注：{{ order.remark }}</span>
						</div>
					</a-space>
				</a-descriptions-item>
				<a-descriptions-item label="备注" :span="3">{{ bizSaleProjectInvoice.remark }}</a-descriptions-item>
			</a-descriptions>
			<a-table
				class="shipment-items"
				row-key="id"
				size="small"
				bordered
				:pagination="false"
				:columns="invoiceItemColumns"
				:data-source="invoiceItems"
			>
				<template #bodyCell="{ column, record }">
					<template v-if="column.dataIndex === 'shipmentType'">
						<a-tag :color="record.projectProductItemCategory === 'REISSUE_ORDER' ? 'orange' : 'blue'">
							{{ record.projectProductItemCategory === 'REISSUE_ORDER' ? '补发货' : '正常发货' }}
						</a-tag>
					</template>
					<template v-if="column.dataIndex === 'specs'">
						{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SPECS', record.specs) || record.specs || '--' }}
					</template>
					<template v-if="column.dataIndex === 'reissueOrder'">
						<template v-if="record.projectReissueOrderId">
							<a-tag color="orange">补发单</a-tag>
							{{ record.reissueOrderCreateTime || record.projectReissueOrderId }}
						</template>
						<span v-else>--</span>
					</template>
				</template>
			</a-table>
			<br />
		</template>

		<a-empty v-if="list.length === 0" description="暂无实际发货记录" />
	</a-skeleton>
	<a-result v-else status="500" title="500" sub-title="服务器错误">
		<template #extra>
			<a-button type="primary" @click="loadData">重新加载</a-button>
		</template>
	</a-result>
	<processDetails ref="detailRef"></processDetails>
</template>

<script setup lang="js" name="projectInvoice">
	import processDetails from '@/views/biz/bizprocess/processDetails/index.vue'
	import BizSaleProjectInvoiceApi from '@/api/biz/bizSaleProjectInvoiceApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { safeJsonParse } from '@/utils/json'
	import { useProject } from '@/composables/useProject'
	import { message } from 'ant-design-vue'
	const prop = defineProps({
		projectId: String
	})
	const detailRef = ref()
	const loading = ref(false)
	const error = ref(false)
	const list = ref([])
	const deliveryPlans = ref([])
	const exportingPlanId = ref('')
	const { exportProjectInitInvoice } = useProject()
	const exportDeliveryPlan = async (plan) => {
		exportingPlanId.value = String(plan.id)
		try {
			await exportProjectInitInvoice(prop.projectId, plan.id)
		} catch (error) {
			message.error(error?.message || '发货单导出失败')
		} finally {
			exportingPlanId.value = ''
		}
	}
	const normalizePlanList = (result) => {
		if (Array.isArray(result)) return result
		if (Array.isArray(result?.records)) return result.records
		if (Array.isArray(result?.list)) return result.list
		return []
	}
	const deliveryPlanItems = (plan) => {
		let items =
			plan?.productList || plan?.productItemList || plan?.itemList || plan?.items || plan?.projectProductItemList
		if (!Array.isArray(items)) {
			items = safeJsonParse(plan?.itemJson ?? plan?.ITEM_JSON, [])
		}
		return items.map((item, index) => ({
			...item,
			rowKey: item.id || item.projectProductItemId || item.productId || index,
			productName: item.productName || item.PRODUCT_NAME || item.productId || '--',
			amount: item.amount ?? item.AMOUNT ?? item.number ?? 0,
			remark: item.remark || '--'
		}))
	}
	const planStatusText = (status) =>
		({ WAIT_DELIVER: '待发货', WAIT_SHIP: '待发货', SHIPPED: '已发货', CANCELLED: '已取消' })[status] || '待发货'
	const planStatusColor = (status) =>
		({ WAIT_DELIVER: 'orange', WAIT_SHIP: 'orange', SHIPPED: 'green', CANCELLED: 'default' })[status] || 'orange'
	const invoicePlanNo = (invoice) => {
		if (invoice.deliveryPlanNo || invoice.planNo) return invoice.deliveryPlanNo || invoice.planNo
		const plan = deliveryPlans.value.find(
			(item) =>
				String(item.invoiceId || '') === String(invoice.id || '') ||
				String(item.processId || '') === String(invoice.processId || '')
		)
		return plan?.planNo
	}
	const shipmentTypeText = (type) =>
		({ NORMAL: '正常发货', REISSUE: '补发货', MIXED: '正常及补发' })[type] || '正常发货'
	const shipmentTypeColor = (type) =>
		({ NORMAL: 'blue', REISSUE: 'orange', MIXED: 'purple' })[type] || 'blue'
	const loadData = async () => {
		loading.value = true
		error.value = false
		try {
			const [invoiceResult, planResult] = await Promise.all([
				BizSaleProjectInvoiceApi.bizSaleProjectInvoiceList({ projectId: prop.projectId }),
				bizSaleProjectApi
					.bizSaleProjectDeliveryPlanList({ projectId: prop.projectId })
					.catch((planError) => {
						console.warn('发货安排读取失败，继续展示历史发货记录', planError)
						return []
					})
			])
			list.value = invoiceResult
			deliveryPlans.value = normalizePlanList(planResult)
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}
	loadData()

	const invoiceItemColumns = [
		{
			title: '发货类型',
			dataIndex: 'shipmentType',
			width: 110
		},
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '规格',
			dataIndex: 'specs',
			width: 120
		},
		{
			title: '发货仓库',
			dataIndex: 'warehousesName'
		},
		{
			title: '本次发货数量',
			dataIndex: 'amount',
			width: 120
		},
		{
			title: '关联补发单',
			dataIndex: 'reissueOrder',
			width: 230
		},
		{
			title: '备注',
			dataIndex: 'remark'
		}
	]
	const deliveryPlanItemColumns = [
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '安排数量',
			dataIndex: 'amount',
			width: 120
		},
		{
			title: '备注',
			dataIndex: 'remark'
		}
	]
</script>

<style scoped>
	.shipment-items {
		margin-top: 12px;
	}

	.reissue-remark {
		margin-left: 12px;
	}

	.delivery-plan-card {
		margin-bottom: 16px;
	}
</style>
