<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="申请退货单"
		:width="800"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<a-result v-if="error" status="500" title="500" sub-title="服务器错误">
				<template #extra>
					<a-button type="primary" @click="loadInitData">重新加载</a-button>
				</template>
			</a-result>
			<a-tabs v-model:activeKey="activeKey">
				<a-tab-pane :forceRender="true" key="baseInfo" tab="基本信息">
					<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="是否需要退款：" name="refundRequired">
							<a-radio-group v-model:value="formData.refundRequired">
								<a-radio :value="true">需要退款</a-radio>
								<a-radio :value="false">无需退款</a-radio>
							</a-radio-group>
						</a-form-item>
						<a-form-item label="退回金额：" name="amount">
							<XnCurrencyInput :min="0" v-model:value="formData.amount" disabled placeholder="根据退货产品自动计算" />
						</a-form-item>

						<a-form-item label="退回仓库：" name="warehousesId">
							<a-select
								show-search
								:filterOption="filterOption"
								placeholder="请选择退回仓库"
								v-model:value="formData.warehousesId"
								:options="warehousesList"
							></a-select>
						</a-form-item>
						<a-form-item label="物流类型：" name="logisticsCategory">
							<a-select
								placeholder="物流类型"
								v-model:value="formData.logisticsCategory"
								:options="logisticsCategory"
							></a-select>
						</a-form-item>
						<a-form-item label="物流编号：" name="logisticsId">
							<a-input placeholder="请输入物流编号" v-model:value="formData.logisticsId"></a-input>
						</a-form-item>
						<a-form-item label="备注：" name="remark">
							<a-textarea v-model:value="formData.remark" placeholder="请输入备注" allow-clear />
						</a-form-item>
					</a-form>
				</a-tab-pane>

				<a-tab-pane :forceRender="true" key="product-info" tab="产品信息">
					<a-form class="product-form" ref="productFormRef" :model="formData" layout="vertical">
						<a-form-item
							:key="formData.productList"
							style="margin-bottom: 0"
							:name="'productList'"
							:rules="{ required: false, message: '产品必填' }"
						>
							<a-button class="editable-add-btn" style="margin-bottom: 8px" @click="openSelect">添加 </a-button>
						</a-form-item>
						<a-table
							childrenColumnName="chi"
							:pagination="false"
							size="middle"
							rowKey="id"
							bordered
							:data-source="formData.productList"
							:columns="columns"
						>
							<template #bodyCell="{ column, record, index }">
								<template v-if="column.dataIndex === 'productName'">
									{{ record.productName }}
								</template>
								<template v-if="column.dataIndex === 'productCategory'">
									{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
								</template>
								<template v-if="column.dataIndex === 'amount'">
									<a-form-item
										:key="formData.productList[index].id"
										style="margin-bottom: 0"
										:name="['productList', index, 'amount']"
										:rules="{ required: true, message: '数量必填', trigger: 'change' }"
									>
										<a-input-number
											min="1"
											:max="formData.productList[index].max"
											v-model:value="formData.productList[index].amount"
											placeholder=""
											style="width: 100%; margin-right: 8px"
										/>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'remark'">
									<a-form-item
										:key="formData.productList[index].id"
										style="margin-bottom: 0"
										:name="['productList', index, 'remark']"
									>
										<a-input v-model:value="formData.productList[index].remark"></a-input>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'operation'">
									<a-button @click="formData.productList.splice(index, 1)" type="link" danger size="small"
										>删除
									</a-button>
								</template>
							</template>
							<template #expandedRowRender="{ record }">
								<a-table
									:pagination="false"
									size="middle"
									bordered
									:data-source="record.children"
									:columns="childrenColumns"
								>
								</a-table>
							</template>
						</a-table>
					</a-form>
				</a-tab-pane>
				<a-tab-pane v-if="isOpenProcess" :forceRender="true" key="approve-info" tab="审批人信息">
					<a-form ref="approveFormRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item v-if="formData.refundRequired" label="财务：" name="treasurer">
							<xn-user-selector
								:dataIsConverterFlw="false"
								:radioModel="true"
								:org-tree-api="selectorApiFunction.orgTreeApi"
								:user-page-api="selectorApiFunction.userPageApi"
								:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
								v-model:value="formData.treasurer"
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
	<a-modal
		width="800px"
		@ok="onSelect"
		:closable="false"
		:wrap-style="{ overflow: 'hidden' }"
		v-model:open="showSelect"
	>
		<a-table rowKey="id" :rowSelection="rowSelection" :data-source="modalProductList" :columns="modalColumn">
			<template #bodyCell="{ column, record }">
				<template v-if="column.dataIndex === 'category'">
					{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'SALE_PROJECT_ITEM_CATEGORY', record.category) }}
				</template>
			</template>
		</a-table>
	</a-modal>
</template>

<script setup name="startProjectReturnFlowForm">
	import { useLoading } from '@/composables/useLoading'
	import { cloneDeep } from 'lodash-es'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { useProcessParam } from '@/composables/useProcessParam'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { createVNode, ref, useTemplateRef, watch } from 'vue'
	import { Decimal } from 'decimal.js'
	import { required } from '@/utils/formRules'
	import SelectProductModal from '@/views/biz/bizproduct/modal/selectProductModal/index.vue'
	import warehousesApi from '@/api/biz/warehousesApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { filterOption } from 'ant-design-vue/es/vc-mentions/src/util'
	import tool from '@/utils/tool'

	const logisticsCategory = ref()

	const productFormRef = useTemplateRef('productFormRef')
	const formRef = useTemplateRef('formRef')
	const openSelect = async () => {
		showSelect.value = true
	}
	const onSelect = () => {
		let arr = currentSelect.map((v) => {
			let max = v.number
			return {
				id: v.id,
				projectProductItemId: v.id,
				productCategory: v.productCategory,
				productName: v.productName,
				amount: v.number,
				productId: v.productId,
				price: v.price,
				number: v.number,
				children: v.children,
				remark: '',
				max: max
			}
		})

		formData.value.productList.push(...arr)
		currentSelect = []
		showSelect.value = false
	}
	const emit = defineEmits({ successful: null })
	const showSelect = ref(false)
	const warehousesList = ref([])
	const allProductList = ref([])
	const selectorApiFunction = useUserSelector()
	const { isOpenProcess, copyUserIdList, approveUserIdList, rule, treasurer } = useProcessParam(
		'Process_sale_project_product_return'
	)

	const formRules = computed(() => ({
		...rule,
		refundRequired: [
			{
				validator: (_rule, value) =>
					typeof value === 'boolean' ? Promise.resolve() : Promise.reject(new Error('请选择是否需要退款')),
				trigger: 'change'
			}
		],
		...(formData.value.refundRequired ? { treasurer: [required('请选择财务')] } : {}),
			projectId: [required('项目编号必填')],
			productList: [required('产品列表必选')],
			warehousesId: [required('仓库编号')]
	}))
	const modalColumn = ref([
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '20%'
		},
		{
			title: '发货单类型',
			width: '20%',
			dataIndex: 'category'
		},

		{
			title: '数量',
			width: '10%',
			dataIndex: 'number'
		},
		{
			title: '已发货',
			dataIndex: 'delivery',
			width: '10%'
		},

		{
			title: '备注',
			dataIndex: 'remark'
		}
	])
	const visible = ref(false)
	const activeKey = ref('baseInfo')
	let currentSelect = []
	const rowSelection = ref({
		onChange: (selectedRowKey, selectedRows) => {
			currentSelect = selectedRows
		}
	})

	const modalProductList = computed(() => {
		const list = allProductList.value.filter((v) => {
			return formData.value.productList.every((p) => p.projectProductItemId != v.id)
		})
		return list
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
	const childrenColumns = [
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '20%'
		},

		{
			title: '数量',
			width: '10%',
			dataIndex: 'number'
		}
	]
	const formData = ref({})
	watch(
		() => formData.value.productList,
		(productList) => {
			formData.value.amount = (productList || [])
				.reduce((total, item) => {
					const number = new Decimal(item.number || 0)
					if (number.lte(0)) return total
					const lineAmount = new Decimal(item.price || 0)
						.mul(item.amount || 0)
						.div(number)
						.toDecimalPlaces(2)
					return total.add(lineAmount)
				}, new Decimal(0))
				.toFixed(2)
		},
		{ deep: true }
	)
	watch(
		() => formData.value.refundRequired,
		(refundRequired) => {
			if (!refundRequired) {
				formData.value.treasurer = ''
			} else if (!formData.value.treasurer) {
				formData.value.treasurer = treasurer
			}
		}
	)
	const approveFormRef = useTemplateRef('approveFormRef')
	const onOpen = async (record) => {
		visible.value = true

		formData.value = {
			projectId: record.id,
			refundRequired: true,
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList,
			treasurer: treasurer,
			productList: []
		}
		activeKey.value = 'baseInfo'
		await loadInitData()
	}
	const onClose = () => {
		emit('successful')
		visible.value = false
	}
	const { load: onSubmit, loading: sendLoading } = useLoading(async () => {
		try {
			await formRef.value.validate()
		} catch (e) {
			activeKey.value = 'baseInfo'

			return
		}

		if (activeKey.value === 'baseInfo') {
			activeKey.value = 'product-info'
			return
		}

		try {
			await productFormRef.value.validate()
		} catch (e) {
			activeKey.value = 'product-info'
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

		await bizProcessApi.bizProcessReturnProjectProductItem(form)
		onClose()
	})

	const {
		load: loadInitData,
		error,
		loading
	} = useLoading(async () => {
		let res = await warehousesApi.warehousesList()
		logisticsCategory.value = tool.dictList('LOGISTICS_CATEGORY')
		warehousesList.value = res.map((v) => {
			return {
				label: v.name,
				value: v.id
			}
		})
		res = await bizSaleProjectApi.bizSaleProjectProductItemList({ id: formData.value.projectId })

		allProductList.value = res.filter((v) => {
			return v.state === 'SHIPPED'
		})
		const { bizSaleProject } = await bizSaleProjectApi.bizSaleProjectDetail({ id: formData.value.projectId })
		formData.value.consignee = bizSaleProject.consignee
		formData.value.phone = bizSaleProject.phone
		formData.value.unit = bizSaleProject.unit
		formData.value.address = bizSaleProject.address
		formData.value.freightCategory = bizSaleProject.freightCategory
		formData.value.freight = bizSaleProject.freight
	})

	defineExpose({
		onOpen
	})
</script>

<style scoped>
	::v-deep(.product-form .ant-form-item) {
		margin-bottom: 0;
	}
</style>
