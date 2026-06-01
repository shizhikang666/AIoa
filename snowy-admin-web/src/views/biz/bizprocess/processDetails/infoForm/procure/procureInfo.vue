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
					<template #bodyCell="{ column, text, record, index }">
						<template v-if="column.dataIndex === 'productName'">{{ record.productName }}</template>
						<template v-if="column.dataIndex === 'specs'">
							{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SPECS', record.specs) }}
						</template>
						<template v-if="column.dataIndex === 'link'">
							<div v-html="highlightedText(record.link)"></div>
							<!--							<a-typography-link target="_blank" :href="record.link">{{ record.link }}</a-typography-link>-->
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
				v-if="details.productList.length"
				:pagination="false"
				size="middle"
				bordered
				:data-source="details.productList"
				:columns="columns"
			>
				<template #bodyCell="{ column, text, record, index }">
					<template v-if="column.dataIndex === 'productName'">
						<a-typography-link @click="openProductDetails(record.productId)"
							>{{ record.productName }}
						</a-typography-link>
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

	const loading = ref(false)
	const error = ref(false)
	const { id } = defineProps({
		id: {
			type: String,
			required: true
		}
	})

	// 替换网址为高亮版本
	const highlightedText = (text) => {
		if (!text) {
			return ''
		}
		const urlRegex = /https?:\/\/[^\s]+/g
		return text.replace(urlRegex, (url) => {
			return `<a href="${url}" target="_blank" style="color: blue; text-decoration: underline;">${url}</a>`
		})
	}
	const supplier = ref({})
	const details = ref({})
	const load = async () => {
		error.value = false
		loading.value = true
		try {
			const fields = ['supplier', 'amount', 'productList', 'desirePurchaseDate', 'remark', 'productInfoList']
			const res = await bizProcessApi.bizVariable({ id: id, fields })
			let result = {}
			res.forEach((item) => {
				result[item.name] = item.value
			})

			console.log(result)

			result.productList = result.productList ? JSON.parse(result.productList) : []
			supplier.value = result.supplier
			details.value = result
		} catch (e) {
			error.value = true
		} finally {
			loading.value = false
		}
	}

	const productDetailsRef = useTemplateRef('productDetailsRef')

	const openProductDetails = (id) => {
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
