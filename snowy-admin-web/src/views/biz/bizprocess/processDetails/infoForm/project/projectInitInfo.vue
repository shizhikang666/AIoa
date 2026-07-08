<template>
	<a-skeleton v-if="!error" active :loading="loading">
		<a-descriptions bordered title="项目信息" size="small">
			<a-descriptions-item label="项目名称">
				<a-typography-link
					@click="
						projectDetail.onOpen({
							id: projectBaseInfo.id
						})
					"
				>
					{{ projectBaseInfo.projectName }}
				</a-typography-link>
			</a-descriptions-item>
			<a-descriptions-item label="项目状态">
				<a-tag
					:color="$TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE_COLOR', projectBaseInfo.projectState)"
				>
					{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_STATE', projectBaseInfo.projectState) }}
				</a-tag>
			</a-descriptions-item>
			<a-descriptions-item label="项目显示状态">
				{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_VISIBILITY', projectBaseInfo.visibility) }}
			</a-descriptions-item>
			<a-descriptions-item label="项目类别">
				{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'PROJECT_CATEGORY', projectBaseInfo.projectCategory) }}
			</a-descriptions-item>
			<a-descriptions-item label="项目负责人">
				{{ projectBaseInfo.headName }}
			</a-descriptions-item>
			<a-descriptions-item label="项目创建日期">
				{{ projectBaseInfo.createTime }}
			</a-descriptions-item>
			<a-descriptions-item label="成交日期">
				<a-typography-text style="padding-right: 6px"> {{ processInfo.completionDate }}</a-typography-text>
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="收款账户">
				{{ processInfo.accountName }}
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="结算方式">
				{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'payerCategory', processInfo.payerCategory) }}
			</a-descriptions-item>

			<a-descriptions-item label="回扣金额">
				<a-typography-text style="padding-right: 6px" strong>￥ {{ processInfo.rebateAmount }} </a-typography-text>
			</a-descriptions-item>
			<a-descriptions-item label="项目金额">
				<a-typography-text style="padding-right: 6px" strong>￥ {{ processInfo.initPrice }}</a-typography-text>
			</a-descriptions-item>
		</a-descriptions>
		<br />
		<br />
		<a-typography-title :level="5">产品信息</a-typography-title>
		<a-table :pagination="false" size="middle" bordered :data-source="processInfo.productList" :columns="columns">
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'productCategory'">
					{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
				</template>
				<template v-if="column.dataIndex === 'productSysCategory'">
					<a-tag :color="$TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE_COLOR', record.productSysCategory)">
						{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SYS_TYPE', record.productSysCategory) }}
					</a-tag>
				</template>
			</template>
			<template #footer>
				<a-row justify="end">
					共计：
					<a-typography-text style="padding-right: 6px" strong>￥{{ totalPrice }}</a-typography-text>
				</a-row>
			</template>
		</a-table>
		<br />
		<a-descriptions bordered title="收货信息" size="small">
			<a-descriptions-item label="联系人">
				{{ processInfo.consignee }}
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="联系电话">
				{{ processInfo.phone }}
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="收货单位">
				{{ processInfo.unit }}
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="收货地址">
				{{ processInfo.address }}
			</a-descriptions-item>
			<a-descriptions-item label="运费支付方式">
				{{ $TOOL.dictTypeDataByPath('FREIGHT_CATEGORY', processInfo.freightCategory) }}
			</a-descriptions-item>
			<a-descriptions-item label="运费">
				{{ projectBaseInfo.freight }}
			</a-descriptions-item>
		</a-descriptions>

		<br />
		<br />
		<a-descriptions bordered title="开票信息" v-if="processInfo.isInvoicing" size="small">
			<a-descriptions-item label="开票金额">
				{{ invoicingInfo.amount }}
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="开票类型">
				{{ $TOOL.dictTypeDataByPath('InvoicingCategory', invoicingInfo.invoicingCategory) }}
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="开票公司">
				{{ invoicingInfo.companyName }}
			</a-descriptions-item>
			<a-descriptions-item :span="4" label="客户公司">
				{{ invoicingInfo.customerCompany }}
			</a-descriptions-item>
			<a-descriptions-item label="单位全称">
				{{ invoicingInfo.unit }}
			</a-descriptions-item>
			<a-descriptions-item label="单位电话">
				{{ invoicingInfo.unitPhone }}
			</a-descriptions-item>
			<a-descriptions-item label="纳税人号">
				{{ invoicingInfo.taxpayer }}
			</a-descriptions-item>
			<a-descriptions-item label="对公账户">
				{{ invoicingInfo.corporateAccount }}
			</a-descriptions-item>
			<a-descriptions-item label="开户银行">
				{{ invoicingInfo.bankName }}
			</a-descriptions-item>
			<a-descriptions-item label="单位地址">
				{{ invoicingInfo.unitAddress }}
			</a-descriptions-item>
			<a-descriptions-item label="发票收货电话">
				{{ invoicingInfo.phone }}
			</a-descriptions-item>
			<a-descriptions-item label="发票收货地址">
				{{ invoicingInfo.harvestAddress }}
			</a-descriptions-item>
			<a-descriptions-item label="备注">
				{{ invoicingInfo.remark }}
			</a-descriptions-item>
		</a-descriptions>
	</a-skeleton>
	<detail ref="projectDetail"></detail>
</template>

<script setup lang="js">
	import bizProcessApi from '@/api/biz/bizProcessApi'

	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import detail from '@/views/biz/saleproject/detail.vue'
	import { Decimal } from 'decimal.js'

	const processInfo = ref({
		productList: []
	})
	const totalPrice = computed(() => {
		return processInfo.value.productList
			.reduce((sum, item) => {
				return sum.plus(new Decimal(item.price ? item.price : 0))
			}, new Decimal(0))
			.toNumber()
	})

	const columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '20%'
		},

		{
			title: '数量',
			width: '10%',
			dataIndex: 'number'
		},
		{
			title: '单价',
			width: '15%',
			dataIndex: 'unitPrice'
		},

		{
			title: '优惠率',
			width: '10%',
			dataIndex: 'discountRate'
		},

		{
			title: '价格',
			width: '15%',
			dataIndex: 'price'
		},
		{
			title: '备注',

			dataIndex: 'remark'
		}
	]
	const projectDetail = ref()

	const props = defineProps({
		id: {
			type: String,
			required: true
		}
	})
	const projectBaseInfo = ref({})

	const loading = ref(false)
	const error = ref(false)

	const invoicingInfo = ref({})
	const load = async () => {
		error.value = false
		loading.value = true

		try {
			const fields = [
				'projectId',
				'remark',
				'accountId',
				'accountName',
				'initPrice',
				'payerCategory',
				'consignee',
				'unit',
				'address',
				'phone',
				'freightCategory',
				'freight',
				'productList',
				'isInvoicing',
				'invoicingInfo',
				'completionDate',
				'rebateAmount'
			]
			const res = await bizProcessApi.bizVariable({ id: props.id, fields })
			const result = {}
			res.forEach((item) => {
				result[item.name] = item.value
			})
			processInfo.value = result
			const details = await bizSaleProjectApi.bizSaleProjectDetail({ id: result.projectId })
			projectBaseInfo.value = details.bizSaleProject
			processInfo.value.accountName = result.accountName || result.accountId
			invoicingInfo.value = processInfo.value.invoicingInfo
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	load()
</script>

<style scoped></style>
