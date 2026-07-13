<template>
	<xn-form-container
		:title="formData.id ? '编辑产品' : '增加产品'"
		:width="700"
		:bodyStyle="{ paddingTop: 0 }"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-tabs v-model:activeKey="activeKey">
			<a-tab-pane key="info" tab="基本信息">
				<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
					<a-form-item label="产品名称：" name="productName">
						<a-input v-model:value="formData.productName" placeholder="请输入产品名称" allow-clear />
					</a-form-item>
					<a-form-item label="产品分类：" name="productCategory">
						<a-select
							v-model:value="formData.productCategory"
							placeholder="请选择产品分类"
							:options="productCategoryOptions"
						/>
					</a-form-item>
					<a-form-item label="产品规格：" name="specs">
						<a-select v-model:value="formData.specs" placeholder="请选择产品规格" :options="productSpecsCategory" />
					</a-form-item>
					<a-form-item label="安全预警库存：" name="safetyStock">
						<a-input-number
							v-model:value="formData.safetyStock"
							placeholder="请输入安全预警库存"
							:min="0"
							:max="10000"
							style="width: 100%"
						/>
					</a-form-item>
					<a-form-item label="采购单价：" name="purchasePrice">
						<xn-currency-input v-model:value="formData.purchasePrice" placeholder="请输入采购单价"></xn-currency-input>
					</a-form-item>
					<a-form-item label="销售单价：" name="salePrice">
						<xn-currency-input v-model:value="formData.salePrice" placeholder="请输入销售单价"></xn-currency-input>
					</a-form-item>
					<a-form-item label="最低销售单价：" name="minPrice">
						<xn-currency-input v-model:value="formData.minPrice" placeholder="请输入最低单价"></xn-currency-input>
					</a-form-item>
				</a-form>
			</a-tab-pane>
			<a-tab-pane v-if="formData.category === 'KIT_PRODUCT'" key="products" tab="套件产品">
				<a-row>
					<a-col span="3">
						<a-button class="editable-add-btn" style="margin-bottom: 8px" @click="handleAdd">添加</a-button>
					</a-col>
					<a-col span="12" v-if="kitProductForm.productList.length === 0 && !isEdit">
						<a-typography-text type="danger"> 套件产品至少添加一项！</a-typography-text>
					</a-col>
				</a-row>
				<a-row></a-row>
				<a-form
					:rules="kitProductFormRules"
					class="kit-product-form"
					layout="horizontal"
					ref="productListFormRef"
					:model="kitProductForm"
				>
					<a-table
						size="small"
						:loading="detailsLoading"
						:scroll="{ y: 500 }"
						:pagination="false"
						bordered
						:data-source="kitProductForm.productList"
						:columns="columns"
					>
						<template #bodyCell="{ column, text, record, index }">
							<template v-if="column.dataIndex === 'productName'">
								<div class="editable-cell">
									<a class="editable-cell-text-wrapper">
										{{ text || ' ' }}
									</a>
								</div>
							</template>
							<template v-if="column.dataIndex === 'productCategory'">
								{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
							</template>
							<template v-if="column.dataIndex === 'number'">
								<a-form-item
									:key="kitProductForm.productList[index].id"
									style="margin-bottom: 0"
									:name="['productList', index, 'number']"
									:rules="{
										required: true,
										message: '数量不能为空',
										trigger: 'change'
									}"
								>
									<a-input-number
										min="1"
										v-model:value="kitProductForm.productList[index].number"
										placeholder="请输入数量"
										style="width: 60%; margin-right: 8px"
									/>
								</a-form-item>
							</template>
							<template v-if="column.dataIndex === 'operation'">
								<a-button @click="kitProductForm.productList.splice(index, 1)" type="link" danger size="small"
									>删除
								</a-button>
							</template>
						</template>
						<template #footer>
							<a-typography-title :level="5">共计：{{ totalAmount }}</a-typography-title>
						</template>
					</a-table>
				</a-form>
			</a-tab-pane>
		</a-tabs>

		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="editSingleForm">
	import { computed, reactive, ref } from 'vue'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizProductApi from '@/api/biz/bizProductApi'

	import SelectProductModal from './modal/selectProductModal/index.vue'
	import { createVNode } from 'vue'
	import { App } from 'ant-design-vue'
	import { Decimal } from 'decimal.js'

	const activeKey = ref('info')
	const { modal } = App.useApp()
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const detailsLoading = ref(false)
	const productCategoryOptions = ref([])
	const productSpecsCategory = ref([])
	const categoryOptions = ref([])
	const isEdit = ref(false)
	const totalAmount = computed(() => {
		return kitProductForm.value.productList.reduce((per, cur) => {
			return per.add(new Decimal(cur.salePrice).mul(cur.number))
		}, new Decimal(0))
	})
	let columns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '30%'
		},
		{
			title: '产品分类',
			dataIndex: 'productCategory'
		},
		{
			title: '采购价格',
			dataIndex: 'purchasePrice'
		},
		{
			title: '销售价格',
			dataIndex: 'salePrice'
		},
		{
			title: '数量',
			dataIndex: 'number'
		},
		{
			title: '操作',
			dataIndex: 'operation'
		}
	]
	// 单品产品默认要校验的
	const formRules = {
		specs: [required('请选择产品规格')],
		productName: [required('请输入产品名称')],
		productCategory: [required('请选择产品分类')],
		safetyStock: [required('请输入安全库存')],
		purchasePrice: [required('请输入采购价格')],
		salePrice: [required('请输入销售价格')],
		minPrice: [required('请输入最低售价')]
	}
	const productListFormRef = ref()
	const kitProductForm = ref({
		productList: []
	})
	const kitProductFormRules = {
		productList: [
			{
				required: true,
				message: '请添加最少一个产品',
				type: 'array',
				min: 1
			}
		]
	}

	//添加成套产品
	const handleAdd = () => {
		const modelValue = ref([])
		let content = createVNode(SelectProductModal, {
			ignoreIdList: kitProductForm.value.productList.map((v) => v.id),
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
					...item,
					number: 1
				}
			})
			kitProductForm.value.productList.push(...result)
		}

		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	// 验证并提交数据
	const onSubmit = async () => {
		const formDataParam = cloneDeep(formData.value)
		const isAdd = !isEdit.value
		//验证基本数据
		try {
			await formRef.value.validate()
		} catch (e) {
			activeKey.value = 'info'
			return false
		}
		const kitProductParams = cloneDeep(kitProductForm.value)
		//如果是套件类型的
		if (formDataParam.category === 'KIT_PRODUCT') {
			try {
				if (kitProductParams.productList.length === 0) {
					activeKey.value = 'products'
					return
				}
				await productListFormRef.value.validate()
			} catch (e) {
				activeKey.value = 'products'
				return false
			}
		}
		submitLoading.value = true
		bizProductApi
			.bizProductSubmitForm({ ...formDataParam, ...kitProductParams }, formDataParam.id)
			.then(() => {
				onClose()
				emit('successful', { ...formDataParam, ...kitProductParams })
			})
			.finally(() => {
				submitLoading.value = false
			})
	}

	// 打开抽屉
	const onOpen = async (record) => {
		open.value = true
		activeKey.value = 'info'
		productCategoryOptions.value = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_TYPE'])
		categoryOptions.value = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_SYS_TYPE'])
		productSpecsCategory.value = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_SPECS'])
		formData.value.specs = productSpecsCategory.value.length ? productSpecsCategory.value[0].value : ''

		if (record) {
			let recordData = cloneDeep(record)
			let specs = productSpecsCategory.value.length ? productSpecsCategory.value[0].value : ''
			//formData.value = Object.assign({ specs }, recordData)
			isEdit.value = !!recordData.id
			if (!isEdit.value) {
				return
			}
			try {
				detailsLoading.value = true
				const { productList, bizProduct } = await bizProductApi.bizProductDetail({ id: recordData.id })

				formData.value = Object.assign({ specs }, recordData, bizProduct)

				kitProductForm.value.productList = productList.map((v) => ({ ...v.product, number: v.number }))
			} finally {
				detailsLoading.value = false
			}
		}
	}

	// 关闭抽屉
	const onClose = () => {
		formRef?.value?.resetFields()
		formData.value = {}
		productListFormRef?.value?.resetFields()
		kitProductForm.value = { productList: [] }

		open.value = false
	}

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
<style scoped lang="less">
	::v-deep(.kit-product-form .ant-form-item) {
		margin-bottom: 0;
	}
</style>
