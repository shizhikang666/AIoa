<template>
	<xn-form-container title="编辑采购单" :width="700" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-skeleton active :loading="loading">
			<a-form class="product-form" ref="formRef" :model="formData" :rules="formRules" layout="horizontal">
				<br />
				<a-form-item label="采购金额：" name="amount">
					<XnCurrencyInput :min="0.01" v-model:value="formData.amount" placeholder="请输入采购金额" />
				</a-form-item>
				<a-table
					rowKey="projectProductItemId"
					:pagination="false"
					size="middle"
					bordered
					:data-source="formData.productList"
					:columns="columns"
				>
					<template #bodyCell="{ column, text, record, index }">
						<template v-if="column.dataIndex === 'productName'">
							{{ record.productName }}
						</template>
						<template v-if="column.dataIndex === 'productCategory'">
							{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
						</template>
						<template v-if="column.dataIndex === 'number'"></template>

						<template v-if="column.dataIndex === 'unitAmount'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'unitAmount']"
								:rules="{ required: true, message: '单价必填', trigger: 'change' }"
							>
								<a-input-number
									min="1"
									@change="changeProductNumber(index)"
									v-model:value="formData.productList[index].unitAmount"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>
						<template v-if="column.dataIndex === 'shippingCost'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'shippingCost']"
								:rules="{ required: true, message: '单件运费必填', trigger: 'change' }"
							>
								<a-input-number
									min="1"
									@change="changeProductNumber(index)"
									v-model:value="formData.productList[index].shippingCost"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>
						<template v-if="column.dataIndex === 'discountRate'">
							<a-form-item
								:key="formData.productList[index].id"
								style="margin-bottom: 0"
								:name="['productList', index, 'discountRate']"
								:rules="{ required: true, message: '必须填写', trigger: 'change' }"
							>
								<a-input-number
									:precision="2"
									:formatter="(value) => `${value}%`"
									:parser="(value) => value.replace('%', '')"
									@change="changeProductNumber(index)"
									min="0"
									v-model:value="formData.productList[index].discountRate"
									placeholder="优惠率"
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>

						<template v-if="column.dataIndex === 'amount'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'amount']"
								:rules="{ required: true, message: '数量必填', trigger: 'change' }"
							>
								<a-input-number
									min="1"
									v-model:value="formData.productList[index].amount"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>

						<template v-if="column.dataIndex === 'remark'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'remark']"
							>
								<a-input v-model:value="formData.productList[index].remark"></a-input>
							</a-form-item>
						</template>
					</template>
					<template #footer>
						<a-row justify="end">
							<a-form-item label="" name="amount">
								¥{{ totalPrice }}
								<!--											<XnCurrencyInput-->
								<!--												disabled="disabled"-->
								<!--												:min="0"-->
								<!--												:value="totalPrice"-->
								<!--												placeholder="请添加产品"-->
								<!--											/>-->
							</a-form-item>
						</a-row>
					</template>
				</a-table>
			</a-form>
		</a-skeleton>

		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizPurchaseOrderForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import { useLoading } from '@/composables/useLoading'
	import { Decimal } from 'decimal.js'
	import { computed } from 'vue'

	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const changeProductNumber = (index) => {
		const product = formData.value.productList[index]
		if (product.number && product.unitAmount) {
			const discount = new Decimal(product.discountRate ? product.discountRate : 0).div(100) // 将百分比转换为小数
			let amount = new Decimal(product.unitAmount).times(product?.number)
			formData.value.productList[index].amount = amount.minus(amount.times(discount))
		}
	}
	const totalPrice = computed(() => {
		return formData.value.productList
			? formData.value.productList
					.reduce((sum, item) => {
						return sum.plus(new Decimal(item.amount ? item.amount : 0))
					}, new Decimal(0))
					.toNumber()
			: 0
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
		// {
		// 	title: '单件运费',
		// 	width: '15%',
		// 	dataIndex: 'shippingCost'
		// },
		{
			title: '备注',

			dataIndex: 'remark'
		}
	]

	const {
		loading,
		load: onOpen,
		error
	} = useLoading(async (record) => {
		open.value = true
		let recordData = cloneDeep(record)
		formData.value = Object.assign({}, recordData)
		const res = await bizPurchaseOrderApi.bizPurchaseOrderDetail({ id: record.id })

		formData.value.productList = res.bizPurchaseOrderItemList

		console.log(res)
	})

	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = Object.assign({
		productList: [required('采购产品必填')],
		amount: [required('采购金额必填')]
	})
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizPurchaseOrderApi
					.bizPurchaseOrderSubmitForm(formDataParam, formDataParam.id)
					.then(() => {
						onClose()
						emit('successful')
					})
					.finally(() => {
						submitLoading.value = false
					})
			})
			.catch(() => {})
	}
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
<style scoped>
	::v-deep(.product-form .ant-form-item) {
		margin-bottom: 0;
	}
</style>
