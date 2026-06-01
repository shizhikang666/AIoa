<template>
	<xn-form-container title="详细信息" :width="700" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-skeleton v-if="!error" :loading="loading">
			<a-descriptions bordered title="基本信息" size="small">
				<a-descriptions-item label="供应商名称">{{ supplier.name }}</a-descriptions-item>
				<a-descriptions-item label="别名">{{ supplier.aliasName }}</a-descriptions-item>
				<a-descriptions-item label="联系人">{{ supplier.contacts }}</a-descriptions-item>
				<a-descriptions-item label="联系电话">{{ supplier.phone }}</a-descriptions-item>
				<a-descriptions-item label="开户行">{{ supplier.bankName }}</a-descriptions-item>
				<a-descriptions-item label="银行账户">{{ supplier.bankAccount }}</a-descriptions-item>
				<a-descriptions-item label="供应商状态">
					{{ $TOOL.dictTypeData('COMMON_STATUS', supplier.status) }}
				</a-descriptions-item>
				<a-descriptions-item label="企业性质">{{ supplier.enterpriseNature }}</a-descriptions-item>
				<a-descriptions-item label="税务登记号">{{ supplier.taxRegistrationNumber }}</a-descriptions-item>
				<a-descriptions-item label="结算方式">{{ supplier.paymentMethod }}</a-descriptions-item>
			</a-descriptions>
			<br />
			<a-table :columns="columns" :data-source="purchaseOrderList">
				<template #bodyCell="{ column, record }">
					<template v-if="column.dataIndex === 'id'">
						<a-typography-link @click="bizPurchaseOrderDetailsRef.onOpen(record)">{{ record.id }} </a-typography-link>
					</template>
				</template>
				<template #footer>
					<a-row justify="end">
						<a-space>
							<a-typography-title :level="5">共计：{{ totalAmount }}</a-typography-title>
						</a-space>
					</a-row>
				</template>
			</a-table>
		</a-skeleton>
	</xn-form-container>

	<bizPurchaseOrderDetails ref="bizPurchaseOrderDetailsRef" />
</template>
<script setup lang="js" name="supplierDetails">
	import { useLoading } from '@/composables/useLoading'
	import SupplierApi from '@/api/biz/supplierApi'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import bizPurchaseOrderDetails from '@/views/biz/bizpurchaseorder/details/index.vue'
	import { useTemplateRef } from 'vue'
	import { Decimal } from 'decimal.js'

	const open = ref(false)
	const supplier = ref({})
	const purchaseOrderList = ref([])
	const bizPurchaseOrderDetailsRef = useTemplateRef('bizPurchaseOrderDetailsRef')
	const { loading, load, error } = useLoading(async (id) => {
		supplier.value = await SupplierApi.supplierDetail({
			id
		})
		purchaseOrderList.value = await bizPurchaseOrderApi.bizPurchaseOrderList({
			supplierName: supplier.value.name.trim()
		})
	})

	const totalAmount = computed(() => {
		return purchaseOrderList.value.reduce((acc, cur) => {
			return acc.add(new Decimal(cur.amount))
		}, new Decimal(0))
	})

	const columns = [
		{
			title: '采购单号',
			dataIndex: 'id',
			width: 200,
			ellipsis: true
		},
		{
			title: '标题',
			dataIndex: 'title',
			ellipsis: true
		},
		{
			title: '备注',
			dataIndex: 'remark',
			ellipsis: true
		},
		{
			title: '金额',
			dataIndex: 'amount'
		},

		{
			title: '创建时间',
			dataIndex: 'createTime',
			ellipsis: true
		}
	]

	const onOpen = async (id) => {
		open.value = true
		load(id)
	}

	const onClose = () => {
		open.value = false
	}

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>

<style scoped></style>
