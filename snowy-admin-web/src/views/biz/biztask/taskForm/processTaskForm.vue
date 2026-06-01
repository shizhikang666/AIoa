<template>
	<a-comment>
		<template #avatar>
			<a-avatar :src="userInfo.avatar" :alt="userInfo.name" />
		</template>
		<template #content>
			<a-form-item>
				<a-textarea v-model:value="form.comment" placeholder="审批意见" :rows="4" />
			</a-form-item>
			<a-form-item>
				<a-space>
					<a-button v-if="hasPerm([premKey])" type="primary" @click="open = true"> 同意</a-button>
					<a-button @click="refuse(false)" :loading="submitting" danger type="primary"> 拒绝</a-button>
				</a-space>
			</a-form-item>
		</template>
	</a-comment>
	<xn-form-container
		width="900px"
		title="采购单详细表单"
		:bodyStyle="{ paddingTop: 0 }"
		v-model:open="open"
		:destroy-on-close="true"
		@close="open = false"
	>
		<a-form class="product-form" ref="productFormRef" :model="formData" :rules="formRules" layout="horizontal">
			<br />
			<a-form-item label="采购金额：" name="amount">
				<XnCurrencyInput :min="0.01" v-model:value="formData.amount" placeholder="请输入采购金额" />
			</a-form-item>
			<a-form-item
				:key="formData.productList"
				style="margin-bottom: 0"
				:name="'productList'"
				:rules="{ required: true, message: '采购产品必填' }"
			>
				<a-button class="editable-add-btn" style="margin-bottom: 8px" @click="openSelect"> 添加表单</a-button>
			</a-form-item>
			<div class="add_table">
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
						<template v-if="column.dataIndex === 'number'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'number']"
								:rules="{ required: true, message: '数量必填', trigger: 'change' }"
							>
								<a-input-number
									@change="changeProductNumber(index)"
									min="1"
									v-model:value="formData.productList[index].number"
									placeholder=""
									style="width: 100%; margin-right: 8px"
								/>
							</a-form-item>
						</template>

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
						<template v-if="column.dataIndex === 'shippingCost'">
							<a-form-item
								:key="formData.productList[index].productId"
								style="margin-bottom: 0"
								:name="['productList', index, 'shippingCost']"
								:rules="{ required: true, message: '单件运费必填', trigger: 'change' }"
							>
								<a-input-number
									min="0"
									@change="changeProductNumber(index)"
									v-model:value="formData.productList[index].shippingCost"
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
						<template v-if="column.dataIndex === 'operation'">
							<a-button @click="formData.productList.splice(index, 1)" type="link" danger size="small">删除 </a-button>
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
			</div>
		</a-form>
		<template #footer>
			<a-button class="xn-mr8" @click="open = false">关闭</a-button>
			<a-button type="primary" @click="submit" :loading="submitting">发送</a-button>
		</template>
	</xn-form-container>
</template>
<script setup lang="js">
	import { computed, createVNode, ref, useTemplateRef } from 'vue'
	import tool from '@/utils/tool'
	import bizTaskApi from '@/api/biz/bizTaskApi'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import { Decimal } from 'decimal.js'
	import SelectProductModal from '@/views/biz/bizproduct/modal/selectProductModal/index.vue'
	import { App } from 'ant-design-vue'

	const { modal } = App.useApp()
	const open = ref(false)
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
			width: '15%',
			dataIndex: 'amount'
		},

		{
			title: '备注',

			dataIndex: 'remark'
		},
		{
			title: '操作',
			width: '100px',
			dataIndex: 'operation'
		}
	]
	const changeProductNumber = (index) => {
		const product = formData.value.productList[index]
		if (product.number && product.unitAmount) {
			const discount = new Decimal(product.discountRate ? product.discountRate : 0).div(100) // 将百分比转换为小数
			let amount = new Decimal(product.unitAmount).times(product?.number)
			formData.value.productList[index].amount = amount.minus(amount.times(discount))
		}
	}
	const productFormRef = useTemplateRef('productFormRef')
	const totalPrice = computed(() => {
		return formData.value.productList
			.reduce((sum, item) => {
				return sum.plus(new Decimal(item.amount ? item.amount : 0))
			}, new Decimal(0))
			.toNumber()
	})

	const openSelect = () => {
		const modelValue = ref([])
		let content = createVNode(SelectProductModal, {
			ignoreIdList: formData.value.productList.map((v) => v.productId),
			disableSearchFromKey: {
				category: true
			},
			defaultSearchFrom: {
				category: 'SINGLE_PRODUCT'
			},
			modelValue: modelValue,
			'onUpdate:modelValue': (value) => (modelValue.value = value)
		})
		const onOk = () => {
			const result = modelValue.value.map((item) => {
				return {
					productName: item.productName,
					productCategory: item.productCategory,
					productId: item.id,
					number: 1,
					unitAmount: item.purchasePrice,
					discountRate: 0,
					shippingCost: 0,
					amount: item.purchasePrice,
					remark: ''
				}
			})
			formData.value.productList.push(...result)
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	const handleAdd = () => {
		const modelValue = ref([])
		let content = createVNode(SelectProductModal, {
			ignoreIdList: productFormData.value.productList.map((v) => v.productId),
			disableSearchFromKey: {
				// category:true,
			},
			defaultSearchFrom: {
				// category:'SINGLE_PRODUCT'
			},
			modelValue: modelValue,
			'onUpdate:modelValue': (value) => (modelValue.value = value)
		})
		const onOk = () => {
			const result = modelValue.value.map((item) => {
				return {
					productName: item.productName,
					productCategory: item.productCategory,
					productId: item.id,
					number: 1,
					unitPrice: item.salePrice,
					discountRate: 0,
					price: item.salePrice,
					remark: '',
					shippingCost: 0
				}
			})
			productFormData.value.productList.push(...result)
		}

		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}
	const userInfo = tool.data.get('USER_INFO')
	const submitting = ref(false)
	const props = defineProps({
		taskDetail: {
			type: Object,
			required: true
		}
	})
	const emit = defineEmits({ successful: null })
	const premKey = computed(() => {
		const { processKey, category } = props.taskDetail
		return processKey + '-' + category
	})

	const form = ref({
		comment: '',
		approval: ''
	})
	const formData = ref({
		productList: [],
		amount: ''
	})
	const formRules = Object.assign({
		productList: [required('采购产品必填')],
		amount: [required('采购金额必填')]
	})

	const refuse = async () => {
		submitting.value = true
		form.value.approval = false
		try {
			await bizTaskApi.approve({
				id: props.taskDetail.taskId,
				form: cloneDeep(form.value)
			})
			emit('successful')
		} catch (e) {
			console.error(e)
		} finally {
			submitting.value = false
		}
	}

	const submit = async () => {
		try {
			await productFormRef.value.validate()
		} catch (e) {
			return
		}

		submitting.value = true
		form.value.approval = true
		try {
			const param = Object.assign(cloneDeep(form.value), cloneDeep(formData.value))
			param.productList = JSON.stringify(param.productList)
			await bizTaskApi.approve({
				id: props.taskDetail.taskId,
				form: param
			})
			emit('successful')
		} catch (e) {
			console.error(e)
		} finally {
			submitting.value = false
		}
	}
</script>
<style lang="less" scoped>
	.add_table {
		::v-deep(:where(.css-dev-only-do-not-override-19iuou).ant-form-item) {
			margin-bottom: 0;
		}
	}
</style>
