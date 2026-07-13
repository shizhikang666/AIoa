<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<template v-for="({ bizSaleProjectInvoice, invoiceItems }, i) in list" :key="i">
			<a-descriptions size="small" bordered>
				<template #title>
					<a-space>
						<span>发货信息</span>
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

		<a-empty v-if="list.length === 0" />
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
	const prop = defineProps({
		projectId: String
	})
	const detailRef = ref()
	const loading = ref(false)
	const error = ref(false)
	const list = ref([])
	const shipmentTypeText = (type) =>
		({ NORMAL: '正常发货', REISSUE: '补发货', MIXED: '正常及补发' })[type] || '正常发货'
	const shipmentTypeColor = (type) =>
		({ NORMAL: 'blue', REISSUE: 'orange', MIXED: 'purple' })[type] || 'blue'
	const loadData = async () => {
		loading.value = true
		error.value = false
		try {
			const res = await BizSaleProjectInvoiceApi.bizSaleProjectInvoiceList({
				projectId: prop.projectId
			})
			list.value = res
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
</script>

<style scoped>
	.shipment-items {
		margin-top: 12px;
	}

	.reissue-remark {
		margin-left: 12px;
	}
</style>
