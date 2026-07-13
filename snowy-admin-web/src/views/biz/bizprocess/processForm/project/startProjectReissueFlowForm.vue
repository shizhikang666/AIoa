<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="添加补货单"
		:width="800"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-tabs v-model:activeKey="activeKey">
			<a-tab-pane :forceRender="true" key="productInfo" tab="订单产品">
				<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
					<a-form-item label="金额：" name="amount">
						<XnCurrencyInput :disabled="true" :min="0" v-model:value="formData.amount" placeholder="请输入金额" />
					</a-form-item>
					<a-form-item label="备注：" name="remark">
						<a-textarea v-model:value="formData.remark" placeholder="请输入备注" allow-clear />
					</a-form-item>
				</a-form>

				<a-form class="product-form" ref="productFormRef" :model="formData" layout="vertical">
					<a-form-item
						:key="formData.productList"
						style="margin-bottom: 0"
						:name="'productList'"
						:rules="{ required: true, message: '产品必填' }"
					>
						<a-button class="editable-add-btn" style="margin-bottom: 8px" @click="handleAdd">添加</a-button>
					</a-form-item>

					<a-table
						row-key="productId"
						:pagination="false"
						size="middle"
						bordered
						:data-source="formData.productList"
						:columns="columns"
					>
						<template #bodyCell="{ column, text, record, index }">
							<template v-if="column.dataIndex === 'productName'"> {{ record.productName }}</template>
							<template v-if="column.dataIndex === 'productCategory'">
								{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
							</template>

							<template v-if="column.dataIndex === 'number'">
								<a-form-item
									v-if="!record.isChildren"
									:key="record.productId"
									style="margin-bottom: 0"
									:name="['productList', index, 'number']"
									:rules="{ required: true, message: '数量必填', trigger: 'change' }"
								>
									<a-input-number
										@change="changeProductNumber(record.zIndex)"
										min="1"
										v-model:value="formData.productList[record.zIndex].number"
										placeholder=""
										style="width: 100%; margin-right: 8px"
									/>
								</a-form-item>

								<a-form-item
									v-else
									style="margin-bottom: 0"
									:name="['productList', record.parentIndex, 'children', record.zIndex, 'number']"
									:rules="{ required: true, message: '数量必填', trigger: 'change' }"
								>
									<a-input-number
										@change="changeProductNumber(record.parentIndex)"
										min="1"
										v-model:value="formData.productList[record.parentIndex].children[record.zIndex].number"
										placeholder=""
										style="width: 100%; margin-right: 8px"
									/>
								</a-form-item>
							</template>
							<template v-if="column.dataIndex === 'unitPrice'">
								<a-form-item
									v-if="!record.isChildren"
									:key="record.productId"
									style="margin-bottom: 0"
									:name="['productList', record.zIndex, 'unitPrice']"
									:rules="{ required: true, message: '单价不能为空', trigger: 'change' }"
								>
									<XnCurrencyInput
										:disabled="true"
										v-model:value="formData.productList[record.zIndex].unitPrice"
										placeholder="请输入单价"
										style="width: 100%"
									/>
								</a-form-item>
							</template>

							<template v-if="column.dataIndex === 'discountRate'">
								<a-form-item
									v-if="!record.isChildren"
									:key="record.productId"
									style="margin-bottom: 0"
									:name="['productList', record.zIndex, 'discountRate']"
									:rules="{ required: true, message: '必须填写', trigger: 'change' }"
								>
									<a-input-number
										:precision="2"
										:formatter="(value) => `${value}%`"
										:parser="(value) => value.replace('%', '')"
										@change="changeProductNumber(record.zIndex)"
										min="0"
										v-model:value="formData.productList[record.zIndex].discountRate"
										placeholder="优惠率"
										style="width: 100%; margin-right: 8px"
									/>
								</a-form-item>
							</template>

							<template v-if="column.dataIndex === 'price'">
								<a-form-item
									v-if="!record.isChildren"
									:key="record.productId"
									style="margin-bottom: 0"
									:name="['productList', record.zIndex, 'price']"
									:rules="{ required: true, message: '价格不能为空', trigger: 'change' }"
								>
									<XnCurrencyInput
										v-model:value="formData.productList[record.zIndex].price"
										placeholder="请输入价格"
										style="width: 100%"
									/>
								</a-form-item>
							</template>
							<template v-if="column.dataIndex === 'remark'">
								<a-form-item
									v-if="!record.isChildren"
									:key="record.productId"
									style="margin-bottom: 0"
									:name="['productList', record.zIndex, 'remark']"
								>
									<a-input v-model:value="formData.productList[record.zIndex].remark"></a-input>
								</a-form-item>
								<!--									<a-form-item-->
								<!--										v-else-->
								<!--										style="margin-bottom: 0"-->
								<!--										:name="['productList', record.parentIndex, 'children', record.zIndex, 'remark']"-->
								<!--									>-->
								<!--										<a-input-->
								<!--											v-model:value="formData.productList[record.parentIndex].children[record.zIndex].remark"-->
								<!--										></a-input>-->
								<!--									</a-form-item>-->
							</template>
							<template v-if="column.dataIndex === 'operation'">
								<a-button @click="removeItem(record)" type="link" danger size="small">删除</a-button>
							</template>
						</template>
						<template #footer>
							<a-row justify="end">
								共计：
								<a-typography-text style="padding-right: 6px" strong>￥{{ totalPrice }} </a-typography-text>
							</a-row>
						</template>
					</a-table>
				</a-form>
			</a-tab-pane>
			<a-tab-pane v-if="isOpenProcess" :forceRender="true" key="approve-info" tab="审批人信息">
				<a-form ref="approveFormRef" :model="formData" :rules="formRules" layout="vertical">
					<a-form-item label="审批人：" name="approveUserIdList">
						<xn-user-selector
							:org-tree-api="selectorApiFunction.orgTreeApi"
							:user-page-api="selectorApiFunction.userPageApi"
							:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
							data-type="object"
							v-model:value="formData.approveUserIdList"
						/>
					</a-form-item>
					<a-form-item label="抄送人：" name="copyUserIdList">
						<xn-user-selector
							:org-tree-api="selectorApiFunction.orgTreeApi"
							:user-page-api="selectorApiFunction.userPageApi"
							:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
							data-type="object"
							v-model:value="formData.copyUserIdList"
						/>
					</a-form-item>
				</a-form>
			</a-tab-pane>
		</a-tabs>

		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="startProjectDeliveryFlowForm">
	import { required, rules } from '@/utils/formRules'
	import { cloneDeep } from 'lodash-es'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { useProcessParam } from '@/composables/useProcessParam'
	import { createVNode, ref, useTemplateRef } from 'vue'
	import SelectProductModal from '@/views/biz/bizproduct/modal/selectProductModal/index.vue'
	import { Decimal } from 'decimal.js'
	import { App } from 'ant-design-vue'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useLoading } from '@/composables/useLoading'
	import { useProduct } from '@/composables/useProduct'

	const approveFormRef = useTemplateRef('approveFormRef')
	const productFormRef = useTemplateRef('productFormRef')
	const formRef = useTemplateRef('formRef')
	const { modal } = App.useApp()
	// 定义emit事件
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	// 表单数据
	const formData = ref({
		projectProductItemList: [],
		productList: []
	})
	const { isOpenProcess, copyUserIdList, approveUserIdList, rule } = useProcessParam('Process_project_reissue_product')
	const selectorApiFunction = useUserSelector()
	// 是否要校验
	const formRules = Object.assign(rule, {
		projectId: [required('项目编号必填')],
		productList: [required('产品列表必选')],
		amount: [required('金额必填')]
	})
	const activeKey = ref('productInfo')
	// 打开抽屉
	const onOpen = async (record) => {
		visible.value = true
		formData.value = {
			projectId: record.id,
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList,
			productList: [],
			amount: 0
		}
		activeKey.value = 'productInfo'
	}
	// 关闭抽屉
	const onClose = () => {
		emit('successful')
		visible.value = false
	}
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
		},
		{
			title: '操作',
			width: '100px',
			dataIndex: 'operation'
		}
	]
	const { warpProduct } = useProduct()
	const updateFormData = () => {
		formData.value.productList.forEach((item, index) => {
			item.isChildren = false
			if (item.children) {
				item.children.forEach((v, childrenIndex) => {
					v.parentIndex = index
					v.zIndex = childrenIndex
					v.isChildren = true
					v.productId = v.id
				})
			}
			item.zIndex = index
		})
	}
	//添加产品信息
	const handleAdd = () => {
		const modelValue = ref([])
		let content = createVNode(SelectProductModal, {
			ignoreIdList: formData.value.productList.map((v) => v.productId),
			disableSearchFromKey: {
				// category:true,
			},
			defaultSearchFrom: {
				// category:'SINGLE_PRODUCT'
			},
			modelValue: modelValue,
			'onUpdate:modelValue': (value) => (modelValue.value = value)
		})
		const onOk = async () => {
			const result = modelValue.value.map((item) => {
				return {
					productName: item.productName,
					productCategory: item.productCategory,
					productId: item.id,
					number: 1,
					unitPrice: item.salePrice,
					discountRate: 0,
					price: item.salePrice,
					remark: ''
				}
			})

			let warProduct = await warpProduct(result, 'productId')
			formData.value.productList.push(...warProduct)
			updateFormData()
		}

		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}
	const removeItem = (record) => {
		if (record.isChildren) {
			formData.value.productList[record.parentIndex].children.splice(record.zIndex, 1)
			if (formData.value.productList[record.parentIndex].children.length === 0) {
				formData.value.productList.splice(record.parentIndex, 1)
			}
		} else {
			formData.value.productList.splice(record.zIndex, 1)
		}
		updateFormData()
	}

	const changeProductNumber = (index) => {
		const product = formData.value.productList[index]
		if (product.number && product.unitPrice) {
			const discount = new Decimal(product.discountRate ? product.discountRate : 0).div(100) // 将百分比转换为小数
			let price = new Decimal(product.unitPrice).times(product?.number)
			formData.value.productList[index].price = price.minus(price.times(discount))
		}
	}
	const totalPrice = computed(() => {
		return formData.value.productList
			.reduce((sum, item) => {
				return sum.plus(new Decimal(item.price ? item.price : 0))
			}, new Decimal(0))
			.toNumber()
	})
	const { load: onSubmit, loading: sendLoading } = useLoading(async () => {
		try {
			await formRef.value.validate()
			await productFormRef.value.validate()
		} catch (e) {
			activeKey.value = 'productInfo'
			return
		}

		if (isOpenProcess.value) {
			try {
				await approveFormRef.value.validate()
			} catch (e) {
				activeKey.value = 'approve-info'
				return
			}
		}
		let form = cloneDeep(formData.value)

		console.log(form)
		await bizProcessApi.bizProcessStartProjectReissue(form)
		onClose()
	})

	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		onOpen
	})
</script>

<style scoped>
	::v-deep(.product-form .ant-form-item) {
		margin-bottom: 0;
	}
</style>
