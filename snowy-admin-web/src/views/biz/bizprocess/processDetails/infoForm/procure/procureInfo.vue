<template>
	<a-skeleton active :loading="loading">
		<template v-if="!error">
			<a-descriptions bordered title="基本信息" size="small">
				<a-descriptions-item :span="2" label="预期采购日期">
					{{ details.desirePurchaseDate }}
				</a-descriptions-item>
				<a-descriptions-item :span="10" label="备注">
					{{ details.remark }}
				</a-descriptions-item>
				<a-descriptions-item label="采购金额">
					{{ details.amount }}
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

			<template v-if="details.productInfoList && details.productInfoList.length">
				<a-typography-title :level="5">申请人采购信息</a-typography-title>
				<br />
				<a-table
					v-if="details.productInfoList && details.productInfoList.length"
					:pagination="false"
					size="middle"
					bordered
					:data-source="details.productInfoList"
					:columns="infoColumns"
				>
					<template #bodyCell="{ column, record }">
						<template v-if="column.dataIndex === 'productName'">{{ record.productName }}</template>
						<template v-if="column.dataIndex === 'specs'">
							{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SPECS', record.specs) }}
						</template>
						<template v-if="column.dataIndex === 'link'">
							<a-typography-link
								v-if="safeHttpUrl(record.link)"
								target="_blank"
								rel="noopener noreferrer"
								:href="safeHttpUrl(record.link)"
							>
								{{ record.link }}
							</a-typography-link>
							<span v-else>{{ record.link }}</span>
						</template>
					</template>
				</a-table>
				<br />
				<br />
				<br />
			</template>

			<a-typography-title v-if="details.productList && details.productList.length" :level="5"
				>采购确认信息
			</a-typography-title>
			<a-table
				v-if="details.productList && details.productList.length"
				:pagination="false"
				size="middle"
				bordered
				:data-source="details.productList"
				:columns="columns"
			>
				<template #bodyCell="{ column, record }">
					<template v-if="column.dataIndex === 'productName'">
						<a-typography-link v-if="canOpenProductDetail" @click="openProductDetails(record.productId)"
							>{{ record.productName }}
						</a-typography-link>
						<span v-else>{{ record.productName }}</span>
					</template>
				</template>
				<template #footer>
					<a-row justify="end">
						共计：
						<a-typography-text style="padding-right: 6px" strong>￥{{ details.amount }}</a-typography-text>
					</a-row>
				</template>
			</a-table>
			<br />
		</template>

		<a-result v-if="error" status="500" title="500" sub-title="服务器错误">
			<template #extra>
				<a-button type="primary" @click="load">重新加载</a-button>
			</template>
		</a-result>
	</a-skeleton>
	<productDetails ref="productDetailsRef"></productDetails>
</template>

<script setup name="procureInfo">
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import supplierApi from '@/api/biz/supplierApi'
	import productDetails from '@/views/biz/bizproduct/details/details.vue'
	import { useTemplateRef } from 'vue'
	import { hasApiPerm } from '@/utils/permission'

	const loading = ref(false)
	const error = ref(false)
	const props = defineProps({
		id: {
			type: String,
			required: true
		}
	})

	// 仅将有效的 HTTP(S) 地址渲染为链接，其余内容按普通文本显示。
	const safeHttpUrl = (value) => {
		if (!value) return ''
		try {
			const url = new URL(String(value).trim())
			return ['http:', 'https:'].includes(url.protocol) ? url.href : ''
		} catch (e) {
			return ''
		}
	}
	const normalizeList = (value) => {
		if (Array.isArray(value)) {
			return value
		}
		if (!value) {
			return []
		}
		if (typeof value === 'string') {
			try {
				const parsed = JSON.parse(value)
				return Array.isArray(parsed) ? parsed : []
			} catch (e) {
				return []
			}
		}
		return []
	}
	const normalizeObject = (value) => {
		if (value && typeof value === 'object' && !Array.isArray(value)) {
			return value
		}
		if (typeof value === 'string') {
			try {
				const parsed = JSON.parse(value)
				return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
			} catch (e) {
				return {}
			}
		}
		return {}
	}
	const supplier = ref({})
	const details = ref({})
	const load = async () => {
		error.value = false
		loading.value = true
		try {
			const fields = ['supplier', 'amount', 'productList', 'desirePurchaseDate', 'remark', 'productInfoList']
			const res = await bizProcessApi.bizVariable({ id: props.id, fields })
			let result = {}
			res.forEach((item) => {
				result[item.name] = item.value
			})

			console.log(result)

			result.productList = normalizeList(result.productList)
			result.productInfoList = normalizeList(result.productInfoList)
			result.supplier = normalizeObject(result.supplier)
			supplier.value = result.supplier
			details.value = result
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	const productDetailsRef = useTemplateRef('productDetailsRef')
	const canOpenProductDetail = hasApiPerm('/biz/bizproduct/detail')

	const openProductDetails = (id) => {
		if (!canOpenProductDetail || !id) {
			return
		}
		productDetailsRef.value.onOpen({ id })
	}

	const infoColumns = [
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
			title: '型号规格',
			width: '10%',
			dataIndex: 'model'
		},
		{
			title: '单位',
			width: '10%',
			dataIndex: 'specs'
		},

		{
			title: '采购链接',

			dataIndex: 'link'
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
			dataIndex: 'unitAmount'
		},
		{
			title: '优惠率',
			width: '10%',
			dataIndex: 'discountRate'
		},

		{
			title: '价格',
			width: '15%',
			dataIndex: 'amount'
		},
		{
			title: '备注',

			dataIndex: 'remark'
		}
	]

	load()
</script>

<style scoped></style>
