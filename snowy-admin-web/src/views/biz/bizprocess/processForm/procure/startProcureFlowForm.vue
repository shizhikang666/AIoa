<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="采购申请"
		:width="1000"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="false">
			<a-result v-if="error" status="500" title="500" sub-title="服务器错误">
				<template #extra>
					<a-button type="primary" @click="loadData">重新加载</a-button>
				</template>
			</a-result>
			<a-tabs v-if="!error" v-model:activeKey="activeKey">
				<a-tab-pane :forceRender="true" key="supplier" tab="供应商信息">
					<a-form ref="supplierFormRef" :model="formData.supplier" :rules="supplierRule" layout="vertical">
						<a-form-item label="供应商名称：" name="name">
							<a-select
								v-model:value="formData.supplier.name"
								show-search
								:show-arrow="false"
								placeholder="输入供应商搜索供应商"
								style="width: 100%"
								:not-found-content="state.fetching ? undefined : null"
								:options="supplierList"
								@change="changeSupplierName"
								@search="loadSupplierList"
							>
								<template v-if="state.fetching" #notFoundContent>
									<a-spin size="small" />
								</template>
							</a-select>
						</a-form-item>
						<a-form-item label="联系人：" name="contacts">
							<a-input v-model:value="formData.supplier.contacts" placeholder="请输入联系人" allow-clear />
						</a-form-item>
						<a-form-item label="联系电话：" name="phone">
							<a-input v-model:value="formData.supplier.phone" placeholder="请输入联系电话" allow-clear />
						</a-form-item>
						<a-form-item label="开户行：" name="bankName">
							<a-input v-model:value="formData.supplier.bankName" placeholder="请输入开户行" allow-clear />
						</a-form-item>
						<a-form-item label="银行账户：" name="bankAccount">
							<a-input v-model:value="formData.supplier.bankAccount" placeholder="请输入银行账户" allow-clear />
						</a-form-item>
						<a-form-item label="企业性质：" name="enterpriseNature">
							<a-input v-model:value="formData.supplier.enterpriseNature" placeholder="请输入企业性质" allow-clear />
						</a-form-item>
						<a-form-item label="税务登记号：" name="taxRegistrationNumber">
							<a-input
								v-model:value="formData.supplier.taxRegistrationNumber"
								placeholder="请输入税务登记号"
								allow-clear
							/>
						</a-form-item>
						<a-form-item label="结算方式：" name="paymentMethod">
							<a-input v-model:value="formData.supplier.paymentMethod" placeholder="请输入结算方式" allow-clear />
						</a-form-item>
					</a-form>
				</a-tab-pane>
				<a-tab-pane :forceRender="true" key="baseInfo" tab="基本信息">
					<a-form class="product-form" ref="formRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="预计采购日期：" name="desirePurchaseDate">
							<a-date-picker
								v-model:value="formData.desirePurchaseDate"
								value-format="YYYY-MM-DD HH:mm:ss"
							></a-date-picker>
						</a-form-item>
						<br />

						<br />
						<a-table
							rowKey="index"
							:pagination="false"
							size="middle"
							bordered
							:data-source="formData.productInfoList"
							:columns="columns"
						>
							<template #bodyCell="{ column, text, record, index }">
								<template v-if="column.dataIndex === 'productName'">
									<a-form-item style="margin-bottom: 0" :name="['productInfoList', index, 'productName']">
										<a-input v-model:value="formData.productInfoList[index].productName"></a-input>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'specs'">
									<a-select
										v-model:value="formData.productInfoList[index].specs"
										placeholder="单位"
										:options="productSpecsCategory"
									/>
								</template>
								<template v-if="column.dataIndex === 'model'">
									<a-form-item style="margin-bottom: 0" :name="['productInfoList', index, 'model']">
										<a-input v-model:value="formData.productInfoList[index].model"></a-input>
									</a-form-item>
								</template>

								<template v-if="column.dataIndex === 'number'">
									<a-form-item
										style="margin-bottom: 0"
										:name="['productInfoList', index, 'number']"
										:rules="{ required: true, message: '数量必填', trigger: 'change' }"
									>
										<a-input-number
											min="1"
											:max="formData.productInfoList[index].max"
											v-model:value="formData.productInfoList[index].number"
											placeholder=""
											style="width: 100%; margin-right: 8px"
										/>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'link'">
									<a-form-item style="margin-bottom: 0" :name="['productInfoList', index, 'link']">
										<a-input v-model:value="formData.productInfoList[index].link"></a-input>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'remark'">
									<a-form-item style="margin-bottom: 0" :name="['productInfoList', index, 'remark']">
										<a-input v-model:value="formData.productInfoList[index].remark"></a-input>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'operation'">
									<a-button @click="formData.productInfoList.splice(index, 1)" type="link" danger size="small"
										>删除
									</a-button>
								</template>
							</template>
							<template #footer>
								<a-row justify="center">
									<a-form-item label="" name="productInfoList">
										<a-button @click="formData.productInfoList.push({})">添加</a-button>
									</a-form-item>
								</a-row>
							</template>
						</a-table>

						<br />
						<br />
						<a-form-item label="备注：" name="remark">
							<a-textarea
								v-model:value="formData.remark"
								placeholder="请输入备注"
								:auto-size="{ minRows: 5, maxRows: 5 }"
							/>
						</a-form-item>
					</a-form>
				</a-tab-pane>
				<a-tab-pane tab="附件信息" key="file-list">
					<a-space>
						<a-button type="primary" @click="() => uploadFormRef.openUpload()">
							<UploadOutlined />
							文件上传
						</a-button>
					</a-space>

					<a-list item-layout="horizontal" :data-source="list">
						<template #renderItem="{ item, index }">
							<a-list-item key="item.id">
								<FileViewItem :item="item" @remove="list.splice(index, 1)"></FileViewItem>
							</a-list-item>
						</template>
					</a-list>
				</a-tab-pane>

				<a-tab-pane :forceRender="true" key="approve-info" tab="审批人信息">
					<a-form ref="approveFormRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="采购：" name="procure">
							<xn-user-selector
								:dataIsConverterFlw="false"
								:radioModel="true"
								:org-tree-api="selectorApiFunction.orgTreeApi"
								:user-page-api="selectorApiFunction.userPageApi"
								:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
								v-model:value="formData.procure"
							/>
						</a-form-item>

						<a-form-item label="审批人：" name="approveUserIdList">
							<xn-user-selector
								:org-tree-api="selectorApiFunction.orgTreeApi"
								:user-page-api="selectorApiFunction.userPageApi"
								:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
								data-type="object"
								v-model:value="formData.approveUserIdList"
							/>
						</a-form-item>
						<a-form-item label="总经办审核：" name="approvesGeneralOffice">
							<xn-user-selector
								:org-tree-api="selectorApiFunction.orgTreeApi"
								:user-page-api="selectorApiFunction.userPageApi"
								:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
								data-type="object"
								v-model:value="formData.approvesGeneralOffice"
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
		</a-skeleton>

		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
		</template>
	</xn-form-container>
	<uploadForm ref="uploadFormRef" @successful="onUploadSuccess" />
</template>

<style scoped>
	::v-deep(.product-form .ant-form-item) {
		margin-bottom: 0;
	}

	::v-deep(.product-form .ant-form-item) {
		margin-bottom: 0;
	}
</style>

<script setup name="startProcureFlowForm">
	// 定义emit事件
	import tool from '@/utils/tool'
	import supplierApi from '@/api/biz/supplierApi'
	import userApi from '@/api/sys/userApi'
	import userCenterApi from '@/api/sys/userCenterApi'
	import { required } from '@/utils/formRules'
	import { createVNode, ref, useTemplateRef } from 'vue'
	import SelectProductModal from '@/views/biz/bizproduct/modal/selectProductModal/index.vue'
	import { App } from 'ant-design-vue'
	import { Decimal } from 'decimal.js'
	import { ExclamationCircleOutlined } from '@ant-design/icons-vue'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { cloneDeep } from 'lodash-es'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useProcessParam } from '@/composables/useProcessParam'
	import { openFilePreview } from '@/utils/filePreview'
	import dayjs from 'dayjs'
	import zhCn from 'dayjs/locale/zh-cn'
	import relativeTime from 'dayjs/plugin/relativeTime'

	dayjs.extend(relativeTime)
	// 设置中文显示
	dayjs.locale(zhCn)
	import UploadForm from '@/views/biz/file/uploadForm.vue'
	import { debounce } from 'lodash-es'
	import Form from '@/views/biz/saleprojectinvoicing/form.vue'
	import FileViewItem from '@/components/File/FileViewItem.vue'

	const productSpecsCategory = ref([])
	const supplierList = ref([])
	const state = reactive({
		data: [],
		value: [],
		fetching: false
	})

	let lastFetchId = 0

	const loadSupplierList = debounce(async (value) => {
		if (value === '' || value === null || value === undefined || value.length === 0) {
			return
		}
		lastFetchId += 1
		const fetchId = lastFetchId
		state.data = []
		state.fetching = true
		const res = await supplierApi.supplierListQueryByName({
			name: value
		})
		if (fetchId !== lastFetchId) {
			// for fetch callback order
			return
		}

		let isExit = false
		supplierList.value = res
			.map((v) => {
				if (v.name === value) {
					isExit = true
				}

				return {
					label: v.name,
					value: v.name,
					...v
				}
			})
			.filter((item, index, self) => self.findIndex((t) => t.label === item.label) === index)

		if (!isExit) {
			supplierList.value.push({
				label: value,
				value: value
			})
		}

		formData.value.supplier.name = supplierList.value[0].label
		changeSupplierName(formData.value.supplier.name)
		state.fetching = false
	}, 300)

	const list = ref([])
	const { modal } = App.useApp()
	const onUploadSuccess = (res) => {
		list.value.push(res)
	}

	const uploadFormRef = useTemplateRef('uploadFormRef')
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	const loading = ref(false)
	const sendLoading = ref(false)
	const error = ref(false)
	const loadData = async () => {
		loading.value = true
		error.value = false
		try {
			const res = await supplierApi.supplierList({})
			supplierList.value = res.map((v) => {
				return {
					value: v.id,
					label: v.name
				}
			})
		} catch (e) {
			error.value = false
		} finally {
			loading.value = false
		}
	}

	const changeSupplierName = (name) => {
		const find = supplierList.value.find((v) => {
			return v.name === name
		})

		if (find) {
			formData.value.supplier = Object.assign(formData.value.supplier, find)
		} else {
			formData.value.supplier = {
				name: name
			}
		}
	}

	const activeKey = ref('supplier')

	const formRules = Object.assign({
		desirePurchaseDate: [required('预期采购日期')],
		procure: [required('采购人员必填')]
	})

	const supplierRule = {
		name: [required('请输入供应商名称')],
		contacts: [required('请输入联系人')]
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
			title: '单位',
			width: '10%',
			dataIndex: 'specs'
		},
		{
			title: '型号规格',

			dataIndex: 'model'
		},
		{
			title: '链接',

			dataIndex: 'link'
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
	const defaultFormData = {
		remark: '',
		supplier: {},

		desirePurchaseDate: '',
		approveUserIdList: [],
		copyUserIdList: [],
		productList: [],
		productInfoList: []
	}
	const formData = ref({ ...defaultFormData })
	const formRef = useTemplateRef('formRef')
	const productFormRef = useTemplateRef('productFormRef')
	const approveFormRef = useTemplateRef('approveFormRef')
	const supplierFormRef = useTemplateRef('supplierFormRef')
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
					unitAmount: item.purchasePrice,
					discountRate: 0,
					amount: item.purchasePrice,
					remark: ''
				}
			})
			formData.value.productList = result
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

	const onSubmit = async () => {
		formData.value.amount = totalPrice.value
		try {
			await supplierFormRef.value.validate()
		} catch (e) {
			activeKey.value = 'supplier'
			return
		}
		try {
			await formRef.value.validate()
		} catch (e) {
			activeKey.value = 'baseInfo'
			return
		}

		try {
			await approveFormRef.value.validate()
		} catch (e) {
			console.error(e)
			activeKey.value = 'approve-info'
			return
		}

		if (activeKey.value !== 'approve-info') {
			activeKey.value = 'approve-info'
			return
		}

		// const showConfirm = async () => {
		// 	return new Promise((resolve) => {
		// 		modal.confirm({
		// 			destroyOnClose: true,
		// 			title: '信息',
		// 			icon: createVNode(ExclamationCircleOutlined),
		// 			content: '产品订单总价格和采购金额不一致是否继续',
		// 			onOk() {
		// 				resolve(true)
		// 			},
		// 			onCancel() {
		// 				resolve(false)
		// 			}
		// 		})
		// 	})
		// }
		//
		// if (formData.value.amount !== totalPrice.value) {
		// 	let flag = await showConfirm()
		// 	if (!flag) {
		// 		return
		// 	}
		// }

		try {
			sendLoading.value = true
			const productFormParam = cloneDeep(formData.value)
			const fileIdList = list.value.map((v) => v.id)

			await bizProcessApi.bizProcessStartProcure({
				...productFormParam,
				fileIdList
			})
			onClose()
		} finally {
			sendLoading.value = false
		}
	}

	const selectorApiFunction = useUserSelector()

	const onClose = async () => {
		formData.value.productInfoList = []
		await nextTick()
		formRef.value.resetFields()
		supplierFormRef.value.resetFields()
		approveFormRef.value.resetFields()
		visible.value = false
		emit('successful')
	}

	const onOpen = async () => {
		const { approveUserIdList, isOpenProcess, copyUserIdList, treasurer, rule, procure } =
			useProcessParam('Process_procure')
		productSpecsCategory.value = tool.dictListByPath(['PRODUCT_DICT', 'PRODUCT_SPECS'])
		formData.value = cloneDeep(defaultFormData)
		visible.value = true
		formData.value.approveUserIdList = approveUserIdList
		formData.value.copyUserIdList = copyUserIdList
		formData.value.treasurer = treasurer
		formData.value.procure = procure
		list.value = []
		activeKey.value = 'supplier'
	}
	defineExpose({
		onOpen
	})
</script>
