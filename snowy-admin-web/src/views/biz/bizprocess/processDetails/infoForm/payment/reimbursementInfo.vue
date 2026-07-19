<template>
	<a-skeleton active :loading="loading">
		<a-result v-if="error" status="500" title="500" sub-title="Sorry, the server is wrong.">
			<template #extra>
				<a-button type="primary" @click="load">重新加载</a-button>
			</template>
		</a-result>
		<template v-else>
			<a-descriptions :labelStyle="{ minWidth: '100px' }" :column="2" bordered title="基本信息" size="small">
				<a-descriptions-item label="单号" v-if="info.objectId">
					<a-typography-link
						v-if="canOpenRelatedDetail(info.settlementCategory)"
						@click="open(info.objectId, info.settlementCategory)"
						>{{ info.objectId }}
					</a-typography-link>
					<span v-else>{{ info.objectId }}</span>
				</a-descriptions-item>
				<a-descriptions-item label="报销类型">
					{{
						$TOOL.dictTypeDataByPath(
							'SETTLEMENT_ACCOUNT',
							'SETTLEMENT_CATEGORY',
							'PAY_CATEGORY',
							info.settlementCategory
						)
					}}
				</a-descriptions-item>
				<a-descriptions-item label="收款款人">
					{{ info.payer }}
				</a-descriptions-item>
				<a-descriptions-item label="银行卡号">
					{{ info.bankAccount }}
				</a-descriptions-item>
				<a-descriptions-item label="开户行">
					{{ info.bankName }}
				</a-descriptions-item>
				<a-descriptions-item label="金额">
					{{ info.amount }}
				</a-descriptions-item>
				<a-descriptions-item :span="3" label="备注">
					{{ info.remark }}
				</a-descriptions-item>
				<a-descriptions-item label="预支款">
					<CheckSquareFilled v-if="info.useAdvancePayment" />
					<CloseSquareFilled v-else />
				</a-descriptions-item>
			</a-descriptions>

			<br />
			<a-descriptions bordered title="结算信息" v-if="isUseAccount" size="small">
				<a-descriptions-item label="结算账户">
					{{ account.accountName }}
				</a-descriptions-item>
				<a-descriptions-item label="结算时间">
					{{ info.payerTime }}
				</a-descriptions-item>
			</a-descriptions>
		</template>
	</a-skeleton>
	<biz-purchase-order-detail ref="bizPurchaseOrderDetailRef"></biz-purchase-order-detail>
	<biz-sale-project-detail ref="projectDetailRef"></biz-sale-project-detail>
	<bizLeaveApplicationDetails ref="bizLeaveApplicationDetailsRef" />
</template>

<script setup>
	import { useLoading } from '@/composables/useLoading'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import bizLeaveApplicationDetails from '@/views/biz/bizleaveapplication/details.vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import bizSaleProjectDetail from '@/views/biz/saleproject/detail.vue'
	import bizPurchaseOrderDetail from '@/views/biz/bizpurchaseorder/details/index.vue'
	import { useTemplateRef } from 'vue'
	import { canOpenFullSaleProjectDetail, hasApiPerm } from '@/utils/permission'

	const bizPurchaseOrderDetailRef = useTemplateRef('bizPurchaseOrderDetailRef')
	const projectDetailRef = useTemplateRef('projectDetailRef')
	const { id } = defineProps({
		id: {
			type: String,
			required: true
		}
	})
	const account = ref({})
	const info = ref({})
	const { loading, error, load } = useLoading(async () => {
		const fields = [
			'useAdvancePayment',
			'objectId',
			'remark',
			'amount',
			'accountId',
			'accountName',
			'payer',
			'payerTime',
			'bankAccount',
			'bankName',
			'settlementCategory'
		]
		const res = await bizProcessApi.bizVariable({ id: id, fields })
		const result = {}
		res.forEach((item) => {
			result[item.name] = item.value
		})
		info.value = result
		if (result.accountId) {
			account.value = {
				id: result.accountId,
				accountName: result.accountName || result.accountId
			}
		}
	})
	load()
	const isUseAccount = computed(() => {
		return info.value.accountId ? true : false
	})
	const bizLeaveApplicationDetailsRef = useTemplateRef('bizLeaveApplicationDetailsRef')
	const canOpenProjectDetail = canOpenFullSaleProjectDetail()
	const canOpenPurchaseOrderDetail = hasApiPerm(
		['/biz/bizpurchaseorder/detail', '/biz/process/query/list'],
		'and'
	)
	const canOpenLeaveApplicationDetail = hasApiPerm('/biz/bizleaveapplication/detail')
	const canOpenRelatedDetail = (category) => {
		if (category === 'CUSTOMER_REBATE') {
			return canOpenProjectDetail
		}
		if (category === 'GOODS_EXPENDITURE' || category === 'ProcurementFreight') {
			return canOpenPurchaseOrderDetail
		}
		return category === 'TravelExpenses' && canOpenLeaveApplicationDetail
	}
	const open = (objectId, category) => {
		if (!canOpenRelatedDetail(category)) {
			return
		}
		if (category === 'CUSTOMER_REBATE') {
			projectDetailRef.value.onOpen({ id: objectId })
		} else if (category === 'GOODS_EXPENDITURE' || category === 'ProcurementFreight') {
			bizPurchaseOrderDetailRef.value.onOpen({ id: objectId })
		} else if (category === 'TravelExpenses') {
			bizLeaveApplicationDetailsRef.value.onOpen({ id: objectId })
		}
	}
</script>

<style scoped></style>
