<template>
	<xn-form-container
		:title="formData.id ? '编辑销售项目' : '增加销售项目'"
		width="960px"
		:bodyStyle="{ paddingTop: 0 }"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-tabs v-model:activeKey="activeTab">
			<a-tab-pane :forceRender="true" key="baseInfo" tab="基本信息">
				<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
					<a-form-item label="项目所属客户：" name="customer">
						<a-row justify="space-between">
							<a-col :span="20">
								<a-select
									:disabled="isDeal"
									v-model:value="formData.customer"
									showSearch
									placeholder="选择客户"
									style="width: 100%"
									:filter-option="false"
									:not-found-content="state.fetching ? undefined : null"
									:options="state.data"
									@search="searchCustomer"
								>
									<template v-if="state.fetching" #notFoundContent>
										<a-spin size="small" />
									</template>
								</a-select>
							</a-col>
							<a-col>
								<a-button type="primary" @click="customerFormRef.onOpen()">新增客户</a-button>
							</a-col>
						</a-row>
					</a-form-item>
					<a-form-item label="项目名称：" name="projectName">
						<a-input :disabled="isDeal" v-model:value="formData.projectName" placeholder="请输入项目名称" allow-clear />
					</a-form-item>
					<a-form-item label="项目编号：" name="projectCode">
						<a-input :disabled="isDeal" v-model:value="formData.projectCode" placeholder="请输入项目编号" allow-clear />
					</a-form-item>
					<a-form-item label="类别：" name="projectCategory">
						<a-select
							:disabled="isDeal"
							v-model:value="formData.projectCategory"
							placeholder="请选择类别直采||默认"
							:options="projectCategoryOptions"
						/>
					</a-form-item>
					<a-form-item label="项目地区：" name="area">
						<a-cascader
							:disabled="isDeal"
							:fieldNames="{ label: 'name', value: 'name', children: 'children' }"
							v-model:value="formData.area"
							:options="pacOptions"
							placeholder="选择地区"
						/>
						<!--				<a-input v-model:value="formData.address" placeholder="请输入客户地区" allow-clear />-->
						<!--			-->
					</a-form-item>
					<a-form-item label="备注" name="remark">
						<a-textarea v-model:value="formData.remark" :rows="4" />
					</a-form-item>

					<!--					<a-form-item label="详细地址：" name="detailsAddress">-->
					<!--						<a-input v-model:value="formData.detailsAddress" placeholder="请输入详细地址" allow-clear />-->
					<!--					</a-form-item>-->
					<!--					<a-form-item label="收货人：" name="consignee">-->
					<!--						<a-input v-model:value="formData.consignee" placeholder="请输入" allow-clear />-->
					<!--					</a-form-item>-->

					<!--					<a-form-item label="收货手机号码：" name="phone">-->
					<!--						<a-input v-model:value="formData.phone" placeholder="请输入" allow-clear />-->
					<!--					</a-form-item>-->

					<!--					<a-form-item label="收货单位：" name="unit">-->
					<!--						<a-input v-model:value="formData.unit" placeholder="请输入" allow-clear />-->
					<!--					</a-form-item>-->

					<!--					<a-form-item label="收货地址：" name="address">-->
					<!--						<a-input v-model:value="formData.address" placeholder="请输入" allow-clear />-->
					<!--					</a-form-item>-->

					<!--					<a-form-item label="备注：" name="remark">-->
					<!--						<a-textarea v-model:value="formData.remark" placeholder="请输入备注" allow-clear />-->
					<!--					</a-form-item>-->

					<!--					<a-form-item label="订单金额：" name="initPrice">-->
					<!--&lt;!&ndash;						<xn-currency-input v-model:value="formData.initPrice" placeholder="请输入订单初始金额"  >	</xn-currency-input>&ndash;&gt;-->
					<!--					</a-form-item>-->
				</a-form>
			</a-tab-pane>
			<a-tab-pane v-if="isDeal" :forceRender="true" key="deliveryInfo" tab="收货信息">
				<a-form ref="deliveryFormRef" :model="formData" :rules="formRules" layout="vertical">
					<a-form-item label="收货单位：" name="unit">
						<a-input placeholder="请输入收货单位" v-model:value="formData.unit"></a-input>
					</a-form-item>
					<a-form-item label="收货人：" name="consignee">
						<a-input placeholder="请输入收货人" v-model:value="formData.consignee"></a-input>
					</a-form-item>
					<a-form-item label="联系电话：" name="phone">
						<a-input placeholder="请输入收货人联系方式" v-model:value="formData.phone"></a-input>
					</a-form-item>
					<a-form-item label="收货地址：" name="address">
						<a-input placeholder="请输入收货地址" v-model:value="formData.address"></a-input>
					</a-form-item>
					<a-form-item label="运费支付方式：" name="freightCategory">
						<a-select
							placeholder="请选择运费支付方式"
							v-model:value="formData.freightCategory"
							:options="freightCategoryOptions"
						></a-select>
					</a-form-item>
					<a-form-item label="运费金额：" name="freight">
						<XnCurrencyInput :min="0" v-model:value="formData.freight" placeholder="请输入运费金额" />
					</a-form-item>
					<a-form-item label="指定物流类型：" name="logisticsCategory">
						<a-select
							:allowClear="true"
							placeholder="物流类型"
							v-model:value="formData.logisticsCategory"
							:options="logisticsCategory"
						></a-select>
					</a-form-item>
					<a-form-item label="发货备注" name="deliveryNote">
						<a-textarea v-model:value="formData.deliveryNote" :rows="4" />
					</a-form-item>
				</a-form>
			</a-tab-pane>

			<!--			<a-tab-pane :forceRender="true" key="productInfo" tab="订单产品">-->
			<!--				<a-row>-->
			<!--					<a-col span="3">-->
			<!--						<a-button class="editable-add-btn" style="margin-bottom: 8px" @click="handleAdd">添加</a-button>-->
			<!--					</a-col>-->
			<!--				</a-row>-->
			<!--				<a-row></a-row>-->

			<!--				<a-form class="product-form" ref="productFormRef" :model="productFormData" layout="vertical">-->
			<!--					<a-table-->
			<!--						:pagination="false"-->
			<!--						size="middle"-->
			<!--						bordered-->
			<!--						:data-source="productFormData.productList"-->
			<!--						:columns="columns"-->
			<!--					>-->
			<!--						<template #bodyCell="{ column, text, record, index }">-->
			<!--							<template v-if="column.dataIndex === 'productName'">-->
			<!--								{{ record.productName }}-->
			<!--							</template>-->
			<!--							<template v-if="column.dataIndex === 'productCategory'">-->
			<!--								{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}-->
			<!--							</template>-->

			<!--							<template v-if="column.dataIndex === 'number'">-->
			<!--								<a-form-item-->
			<!--									:key="productFormData.productList[index].id"-->
			<!--									style="margin-bottom: 0"-->
			<!--									:name="['productList', index, 'number']"-->
			<!--									:rules="{ required: true, message: '数量必填', trigger: 'change' }"-->
			<!--								>-->
			<!--									<a-input-number-->
			<!--										@change="changeProductNumber(index)"-->
			<!--										min="1"-->
			<!--										v-model:value="productFormData.productList[index].number"-->
			<!--										placeholder=""-->
			<!--										style="width: 100%; margin-right: 8px"-->
			<!--									/>-->
			<!--								</a-form-item>-->
			<!--							</template>-->
			<!--							<template v-if="column.dataIndex === 'unitPrice'">-->
			<!--								<a-form-item-->
			<!--									:key="productFormData.productList[index].id"-->
			<!--									style="margin-bottom: 0"-->
			<!--									:name="['productList', index, 'unitPrice']"-->
			<!--									:rules="{ required: true, message: '单价不能为空', trigger: 'change' }"-->
			<!--								>-->
			<!--									<XnCurrencyInput-->
			<!--										v-model:value="productFormData.productList[index].unitPrice"-->
			<!--										placeholder="请输入单价"-->
			<!--										style="width: 100%"-->
			<!--									/>-->
			<!--								</a-form-item>-->
			<!--							</template>-->

			<!--							<template v-if="column.dataIndex === 'discountRate'">-->
			<!--								<a-form-item-->
			<!--									:key="productFormData.productList[index].id"-->
			<!--									style="margin-bottom: 0"-->
			<!--									:name="['productList', index, 'discountRate']"-->
			<!--									:rules="{ required: true, message: '必须填写', trigger: 'change' }"-->
			<!--								>-->
			<!--									<a-input-number-->
			<!--										:precision="2"-->
			<!--										:formatter="(value) => `${value}%`"-->
			<!--										:parser="(value) => value.replace('%', '')"-->
			<!--										@change="changeProductNumber(index)"-->
			<!--										min="0"-->
			<!--										v-model:value="productFormData.productList[index].discountRate"-->
			<!--										placeholder="优惠率"-->
			<!--										style="width: 100%; margin-right: 8px"-->
			<!--									/>-->
			<!--								</a-form-item>-->
			<!--							</template>-->

			<!--							<template v-if="column.dataIndex === 'price'">-->
			<!--								<a-form-item-->
			<!--									:key="productFormData.productList[index].id"-->
			<!--									style="margin-bottom: 0"-->
			<!--									:name="['productList', index, 'price']"-->
			<!--									:rules="{ required: true, message: '价格不能为空', trigger: 'change' }"-->
			<!--								>-->
			<!--									<XnCurrencyInput-->
			<!--										v-model:value="productFormData.productList[index].price"-->
			<!--										placeholder="请输入数量"-->
			<!--										style="width: 100%"-->
			<!--									/>-->
			<!--								</a-form-item>-->
			<!--							</template>-->
			<!--							<template v-if="column.dataIndex === 'remark'">-->
			<!--								<a-form-item-->
			<!--									:key="productFormData.productList[index].id"-->
			<!--									style="margin-bottom: 0"-->
			<!--									:name="['productList', index, 'remark']"-->
			<!--								>-->
			<!--									<a-input v-model:value="productFormData.productList[index].remark"></a-input>-->
			<!--								</a-form-item>-->
			<!--							</template>-->

			<!--							<template v-if="column.dataIndex === 'operation'">-->
			<!--								<a-button @click="productFormData.productList.splice(index, 1)" type="link" danger size="small"-->
			<!--									>删除-->
			<!--								</a-button>-->
			<!--							</template>-->
			<!--						</template>-->
			<!--						<template #footer>-->
			<!--							<a-row justify="end">-->
			<!--								共计：-->
			<!--								<a-typography-text style="padding-right: 6px" strong>￥{{ totalPrice }} </a-typography-text>-->
			<!--							</a-row>-->
			<!--						</template>-->
			<!--					</a-table>-->
			<!--				</a-form>-->
			<!--			</a-tab-pane>-->
		</a-tabs>

		<template #footer>
			<a-row justify="end">
				<!--				<a-col style="margin-right: 8px">-->
				<!--					<a-form ref="initPriceFormRef" :model="formData" :rules="formRules">-->
				<!--						<a-form-item label="订单金额：" required name="initPrice">-->
				<!--							<xn-currency-input-->
				<!--								v-model:value="formData.initPrice"-->
				<!--								placeholder="请输入订单初始金额"-->
				<!--							></xn-currency-input>-->
				<!--						</a-form-item>-->
				<!--					</a-form>-->
				<!--				</a-col>-->
				<a-col>
					<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
					<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
				</a-col>
			</a-row>
		</template>
	</xn-form-container>

	<CustomerForm ref="customerFormRef"></CustomerForm>
</template>

<script setup name="bizSaleProjectForm">
	import tool from '@/utils/tool'
	import { cloneDeep, debounce } from 'lodash-es'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import bizProductApi from '@/api/biz/bizProductApi'
	import { createVNode, reactive, watch, ref } from 'vue'
	import CustomerApi from '@/api/biz/customerApi'
	import { required } from '@/utils/formRules'
	import CustomerForm from '../customer/form.vue'
	import SelectProductModal from '@/views/biz/bizproduct/modal/selectProductModal/index.vue'
	import { App } from 'ant-design-vue'
	import { Decimal } from 'decimal.js'
	import customerApi from '@/api/biz/customerApi'
	import { ExclamationCircleOutlined } from '@ant-design/icons-vue'

	const logisticsCategory = ref([])
	logisticsCategory.value = tool.dictList('LOGISTICS_CATEGORY')
	const freightCategoryOptions = ref(tool.dictList('FREIGHT_CATEGORY'))
	const pacOptions = ref([])
	pacOptions.value = tool.pcaDataAll()

	const activeTab = ref('baseInfo')
	const { modal } = App.useApp()
	// 抽屉状态
	const customerFormRef = ref()
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	const initPriceFormRef = ref()
	// 默认要校验的
	const formRules = {
		customer: [required('请选择项目所属客户')],
		projectName: [required('请输入项目名称')],
		// visibility: [required('请选择项目显示状态')],
		projectCategory: [required('请选择项目类型')]
	}
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const projectStateOptions = ref([])
	const playStateOptions = ref([])
	const visibilityOptions = ref([])
	const projectCategoryOptions = ref([])

	const customerList = ref([])
	//远程搜索客户信息
	let lastFetchId = 0
	const state = reactive({
		data: [],
		fetching: false
	})
	const searchCustomer = debounce((value) => {
		lastFetchId += 1
		const fetchId = lastFetchId
		state.data = []
		state.fetching = true
		CustomerApi.customerPage({
			current: 1,
			name: value,
			size: 99
		}).then((result) => {
			if (fetchId !== lastFetchId) {
				return
			}
			state.data = result.records.map((customer) => ({
				label: `${customer.name}`,
				value: customer.id
			}))
			customerList.value = result.records
			state.fetching = false
		})
	}, 300)
	const productFormData = ref({
		productList: []
	})

	const isDeal = ref(false)

	const productFormRef = ref()

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
	//添加产品信息
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
					remark: ''
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
	const changeProductNumber = (index) => {
		const product = productFormData.value.productList[index]
		if (product.number && product.unitPrice) {
			const discount = new Decimal(product.discountRate ? product.discountRate : 0).div(100) // 将百分比转换为小数
			let price = new Decimal(product.unitPrice).times(product?.number)
			productFormData.value.productList[index].price = price.minus(price.times(discount))
		}
	}
	const totalPrice = computed(() => {
		return productFormData.value.productList
			.reduce((sum, item) => {
				return sum.plus(new Decimal(item.price ? item.price : 0))
			}, new Decimal(0))
			.toNumber()
	})

	//加载修改数据
	const error = ref(false)
	let lastGetDataId = 0
	//用来存储历史数据
	let oldProductList = []
	const editDataInitLoading = ref(false)
	const initData = async (projectId) => {
		lastGetDataId += 1
		const fetchId = lastGetDataId
		const result = await bizSaleProjectApi.bizSaleProjectDetail({ id: projectId })
		if (fetchId != lastGetDataId || !open.value || formData.value.id === undefined) {
			return
		}
		productFormData.value.productList = result?.productItems
		oldProductList = cloneDeep(result?.productItems)
	}

	//判断productArray有没有发生更改
	const isProductArrayUnchanged = () => {
		const mapProductItem = (item) => ({
			productId: item.id,
			number: item.number,
			unitPrice: item.unitPrice,
			discountRate: item.discountRate,
			price: item.price,
			remark: item.remark
		})
		const array1 = oldProductList.map(mapProductItem)
		const array2 = productFormData.value.productList.map(mapProductItem)
		return JSON.stringify(array1) === JSON.stringify(array2)
	}

	// 打开抽屉
	const onOpen = async (record) => {
		open.value = true
		isDeal.value = false
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
			formData.value.area = recordData.area

			if (recordData.projectState) {
				isDeal.value = recordData.projectState !== 'FOLLOW'
			}

			state.data = [
				{
					label: record.customerName,
					value: record.customer
				}
			]
			if (record.id) {
				editDataInitLoading.value = true
				try {
					await initData(record.id)
				} catch (e) {
					error.value = true
				} finally {
					editDataInitLoading.value = true
				}
			}
		}
		activeTab.value = 'baseInfo'
		projectStateOptions.value = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_STATE')
		playStateOptions.value = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_PLAY_STATE')
		visibilityOptions.value = tool.dictListByPath('SALE_PROJECT', 'SALE_PROJECT_VISIBILITY')

		formData.value.visibility = visibilityOptions.value[0].value

		projectCategoryOptions.value = tool.dictListByPath('SALE_PROJECT', 'PROJECT_CATEGORY')
	}
	// 关闭抽屉
	const onClose = () => {
		activeTab.value = 'baseInfo'
		formRef.value.resetFields()
		formData.value = {}
		formRef.value.resetFields()
		error.value = false
		editDataInitLoading.value = false
		state.data = []
		productFormData.value = {
			productList: []
		}
		open.value = false
	}

	// 验证并提交数据
	const onSubmit = async () => {
		try {
			submitLoading.value = true
			const formDataParam = cloneDeep(formData.value)
			delete formDataParam.product
			const productFormParam = cloneDeep(productFormData.value)
			if (formDataParam.area && formDataParam.area.join) {
				formDataParam.area = formDataParam.area.join('/')
			}

			if (!isDeal.value) {
				await bizSaleProjectApi.bizSaleProjectSubmitForm(
					{
						...formDataParam,
						productList: isProductArrayUnchanged() ? null : productFormParam.productList
					},
					formDataParam.id
				)
			} else {
				await bizSaleProjectApi.bizSaleProjectEditDealProject(formDataParam)
			}

			onClose()
			emit('successful')
		} finally {
			submitLoading.value = false
		}
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
