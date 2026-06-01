<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="请购单详情"
		:width="1000"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<a-result v-if="error" status="500" title="500" sub-title="Sorry, the server is wrong.">
				<template #extra>
					<a-button type="primary" @click="loadData">重新加载</a-button>
				</template>
			</a-result>
			<template v-else>
				<a-tabs v-model="activeKey">
					<a-tab-pane key="baseInfo" tab="基本信息">
						<a-descriptions bordered title="" size="small">
							<a-descriptions-item label="结算状态">
								<a-tag
									:color="
										$TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'SETTLEMENT_STATUS_COLOR', details.settlementStatus)
									"
								>
									{{ $TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'SETTLEMENT_STATUS', details.settlementStatus) }}
								</a-tag>
							</a-descriptions-item>
							<a-descriptions-item label="入库状态">
								<a-tag
									:color="$TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'STORAGE_STATUS_COLOR', details.storageStatus)"
								>
									{{ $TOOL.dictTypeDataByPath('PURCHASE_ORDER', 'STORAGE_STATUS', details.storageStatus) }}
								</a-tag>
							</a-descriptions-item>

							<a-descriptions-item label="采购总金额">
								{{ details.amount }}
							</a-descriptions-item>
							<a-descriptions-item :span="2" label="预期采购日期">
								{{ details.desirePurchaseDate }}
							</a-descriptions-item>
							<a-descriptions-item :span="10" label="备注">
								{{ details.remark }}
							</a-descriptions-item>
						</a-descriptions>
						<br />
						<a-descriptions bordered title="供应商信息" size="small">
							<a-descriptions-item label="供应商名称">
								{{ supplier.name }}
							</a-descriptions-item>
							<a-descriptions-item label="联系人">
								{{ supplier.contacts }}
							</a-descriptions-item>
							<a-descriptions-item label="联系电话">
								{{ supplier.phone }}
							</a-descriptions-item>
							<a-descriptions-item label="开户行">
								{{ supplier.bankName }}
							</a-descriptions-item>
							<a-descriptions-item label="银行账户">
								{{ supplier.bankAccount }}
							</a-descriptions-item>
							<a-descriptions-item label="企业性质">
								{{ supplier.enterpriseNature }}
							</a-descriptions-item>
							<a-descriptions-item label="税务登记号">
								{{ supplier.taxRegistrationNumber }}
							</a-descriptions-item>
							<a-descriptions-item label="结算方式">
								{{ supplier.paymentMethod }}
							</a-descriptions-item>
						</a-descriptions>
						<br />
						<a-table :pagination="false" size="middle" bordered :data-source="details.productList" :columns="columns">
							<template #bodyCell="{ column, text, record, index }">
								<template v-if="column.dataIndex === 'productName'">
									<a-typography-link @click="openProductDetails(record.productId)">
										{{ record.productName }}
									</a-typography-link>
								</template>
							</template>
							<template #footer>
								<a-row justify="end">
									共计：
									<a-typography-text style="padding-right: 6px" strong>￥{{ details.amount }} </a-typography-text>
								</a-row>
							</template>
						</a-table>
						<br />
						<a-table
							:pagination="false"
							size="middle"
							bordered
							:data-source="details.bizExpenditureRecordList"
							:columns="excolumns"
						>
							<template #bodyCell="{ column, text, record, index }">
								<template v-if="column.dataIndex === 'settlementCategory'">
									{{
										$TOOL.dictTypeDataByPath(
											'SETTLEMENT_ACCOUNT',
											'SETTLEMENT_CATEGORY',
											'PAY_CATEGORY',
											record.settlementCategory
										)
									}}
								</template>
							</template>
							<template #footer>
								<a-row justify="end">
									共计：
									<a-typography-text style="padding-right: 6px" strong>￥{{ totalAmount }} </a-typography-text>
								</a-row>
							</template>
						</a-table>
						<br />
					</a-tab-pane>
					<a-tab-pane :forceRender="true" key="processInfo">
						<template #tab>
							<a-badge :offset="[10, 0]" :count="runtimeCount">
								<span> 流程记录 </span>
							</a-badge>
						</template>

						<purchaseOrderProcess v-model:runtime-count="runtimeCount" :id="id"></purchaseOrderProcess>
					</a-tab-pane>
				</a-tabs>
			</template>
		</a-skeleton>
	</xn-form-container>

	<productDetails ref="productDetailsRef"></productDetails>
</template>
<script setup name="bizPurchaseOrderDetails">
	import productDetails from '@/views/biz/bizproduct/details/details.vue'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import purchaseOrderProcess from './process/index.vue'
	import { useTemplateRef } from 'vue'

	const productDetailsRef = useTemplateRef('productDetailsRef')
	const visible = ref(false)
	const loading = ref(false)
	const error = ref(false)
	const id = ref('')
	const supplier = ref({})
	const details = ref({
		productList: []
	})
	const runtimeCount = ref(0)
	const totalAmount = computed(() => {
		const records = details.value?.bizExpenditureRecordList || []

		// 使用整数计算避免浮点数精度问题
		const totalInCents = records.reduce((total, record) => {
			const amount = parseFloat(record?.amount) || 0
			// 转换为分（或其他最小单位）进行计算
			return total + Math.round(amount * 100)
		}, 0)

		// 转换回元（或其他单位）
		return totalInCents / 100
	})
	const excolumns = [
		{
			title: '支出账号',
			dataIndex: 'accountName',
			width: '15%'
		},
		{
			title: '支出类型',
			width: '10%',
			dataIndex: 'settlementCategory'
		},
		{
			title: '支出时间',
			width: '20%',
			dataIndex: 'payerTime'
		},
		{
			title: '支出金额',
			width: '10%',
			dataIndex: 'amount'
		},

		{
			title: '备注',

			dataIndex: 'remark'
		}
	]
	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '15%'
		},
		{
			title: '数量',
			width: '10%',
			dataIndex: 'number'
		},
		{
			title: '单价',
			width: '10%',
			dataIndex: 'unitAmount'
		},
		{
			title: '优惠率',
			width: '10%',
			dataIndex: 'discountRate'
		},

		{
			title: '价格',
			width: '10%',
			dataIndex: 'amount'
		},
		{
			title: '运费分摊金额',
			width: '15%',
			dataIndex: 'freightShareAmount'
		},

		// {
		// 	title: '含运费单位成本',
		// 	width: '15%',
		// 	dataIndex: 'unitCostWithFreight'
		// },

		{
			title: '备注',

			dataIndex: 'remark'
		}
	]

	const openProductDetails = (id) => {
		productDetailsRef.value.onOpen({ id })
	}
	const activeKey = ref('baseInfo')
	const loadData = async () => {
		loading.value = true
		error.value = false
		try {
			const result = await bizPurchaseOrderApi.bizPurchaseOrderDetail({ id: id.value })
			details.value = {
				...result.bizPurchaseOrder,
				bizExpenditureRecordList: result.bizExpenditureRecordList,
				productList: result.bizPurchaseOrderItemList
			}

			if (result.bizPurchaseOrder.extJson) {
				supplier.value = JSON.parse(result.bizPurchaseOrder.extJson).supplier
			}
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	const onOpen = async (record) => {
		visible.value = true
		id.value = record.id
		await loadData()
	}

	const onClose = () => {
		visible.value = false
	}

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
