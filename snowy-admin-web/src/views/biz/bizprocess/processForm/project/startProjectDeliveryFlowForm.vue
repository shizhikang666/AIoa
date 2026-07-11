<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="添加发货记录"
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
			<a-tabs v-if="!error" v-model:activeKey="activeKey">
				<a-tab-pane :forceRender="true" key="baseInfo" tab="基本信息">
					<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="收货人：" name="consignee">
							<a-input placeholder="请输入收货人" v-model:value="formData.consignee"></a-input>
						</a-form-item>
						<a-form-item label="收货单位：" name="unit">
							<a-input placeholder="请输入收货单位" v-model:value="formData.unit"></a-input>
						</a-form-item>
						<a-form-item label="收货地址：" name="address">
							<a-input placeholder="请输入收货地址" v-model:value="formData.address"></a-input>
						</a-form-item>
						<a-form-item label="联系电话：" name="phone">
							<a-input placeholder="请输入收货人" v-model:value="formData.phone"></a-input>
						</a-form-item>
						<a-form-item label="运费支付方式：" name="freightCategory">
							<a-select
								placeholder="请选择运费支付方式"
								v-model:value="formData.freightCategory"
								:options="freightCategoryOptions"
							></a-select>
						</a-form-item>
						<!--						<a-form-item label="运费金额：" name="freight">-->
						<!--							<XnCurrencyInput :min="0" v-model:value="formData.freight" placeholder="请输入运费金额" />-->
						<!--						</a-form-item>-->
						<a-form-item label="物流类型：" name="logisticsCategory">
							<a-select
								placeholder="物流类型"
								v-model:value="formData.logisticsCategory"
								:options="logisticsCategory"
							></a-select>
						</a-form-item>
						<a-form-item label="物流编号" name="logisticsId">
							<a-input placeholder="请输入物流编号" v-model:value="formData.logisticsId"></a-input>
						</a-form-item>

						<a-form-item label="发货日期：" name="freightTime">
							<a-date-picker
								v-model:value="formData.freightTime"
								value-format="YYYY-MM-DD HH:mm:ss"
								show-time
							></a-date-picker>
						</a-form-item>
						<a-form-item label="备注：" name="remark">
							<a-textarea
								v-model:value="formData.remark"
								placeholder="请输入备注"
								:auto-size="{ minRows: 5, maxRows: 5 }"
							/>
						</a-form-item>
					</a-form>
				</a-tab-pane>
				<a-tab-pane :forceRender="true" key="product" tab="发货表单">
					<a-form class="product-form" ref="productFormRef" :model="formData" layout="vertical">
						<a-form-item
							:key="formData.projectProductItemList"
							style="margin-bottom: 0"
							:name="'projectProductItemList'"
							:rules="{ required: true, message: '发货数量必填' }"
						>
							<a-button class="editable-add-btn" style="margin-bottom: 8px" @click="openSelect">添加表单 </a-button>
						</a-form-item>
						<a-table
							rowKey="projectProductItemId"
							:pagination="false"
							size="middle"
							bordered
							:data-source="formData.projectProductItemList"
							:columns="columns"
						>
							<template #bodyCell="{ column, text, record, index }">
								<template v-if="column.dataIndex === 'productName'">
									{{ record.productName }}
								</template>
								<template v-if="column.dataIndex === 'productCategory'">
									{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_TYPE', record.productCategory) }}
								</template>
								<template v-if="column.dataIndex === 'warehousesId'">
									<a-form-item
										:key="formData.projectProductItemList[index].warehousesId"
										style="margin-bottom: 0"
										:name="['projectProductItemList', index, 'warehousesId']"
										:rules="{ required: true, message: '发货仓库必填', trigger: 'change' }"
									>
										<a-select
											:options="warehousesList"
											v-model:value="formData.projectProductItemList[index].warehousesId"
										></a-select>
									</a-form-item>
								</template>

								<template v-if="column.dataIndex === 'amount'">
									<a-form-item
										:key="formData.projectProductItemList[index].projectProductItemId"
										style="margin-bottom: 0"
										:name="['projectProductItemList', index, 'warehousesId']"
										:rules="{ required: true, message: '数量必填', trigger: 'change' }"
									>
										<a-input-number
											min="1"
											:max="formData.projectProductItemList[index].max"
											v-model:value="formData.projectProductItemList[index].amount"
											placeholder=""
											style="width: 100%; margin-right: 8px"
										/>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'remark'">
									<a-form-item
										:key="formData.projectProductItemList[index].projectProductItemId"
										style="margin-bottom: 0"
										:name="['projectProductItemList', index, 'remark']"
									>
										<a-input v-model:value="formData.projectProductItemList[index].remark"></a-input>
									</a-form-item>
								</template>
								<template v-if="column.dataIndex === 'operation'">
									<a-button @click="formData.projectProductItemList.splice(index, 1)" type="link" danger size="small"
										>删除
									</a-button>
								</template>
							</template>
						</a-table>
					</a-form>
				</a-tab-pane>
				<a-tab-pane v-if="showApprovalFlow" :forceRender="true" key="approve-info" tab="审批人信息">
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
		</a-skeleton>
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
					<template v-if="column.dataIndex === 'productSpecs'">
						{{ $TOOL.dictTypeDataByPath('PRODUCT_DICT', 'PRODUCT_SPECS', record.productSpecs || record.specs) }}
					</template>
				</template>
			</a-table>
		</a-modal>

		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="startProjectDeliveryFlowForm">
	import { required, rules } from '@/utils/formRules'
	import { message } from 'ant-design-vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import userApi from '@/api/sys/userApi'
	import userCenterApi from '@/api/sys/userCenterApi'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import warehousesApi from '@/api/biz/warehousesApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useProcessParam } from '@/composables/useProcessParam'
	import dayjs from 'dayjs'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData, findWarehouseDeptId, findCompanyByDeptId } = useOrg()

	const sendLoading = ref(false)
	// 定义emit事件
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	const formRef = ref()
	// 表单数据
	const formData = ref({
		projectProductItemList: []
	})

	// 默认要校验的
	const formRules = {
		consignee: [required('收货人信息必填')],
		unit: [required('收货单位必填')],
		address: [required('收获单位必填')],
		phone: [required('请输入手机号')],
		logisticsCategory: [required('物流类型必填')],
		logisticsId: [required('物流编号')],
		freightCategory: [required('运费支付类型必填')],

		freightTime: [required('发货时间')]
	}
	const { isOpenProcess, copyUserIdList, approveUserIdList } = useProcessParam('Process_sale_project_delivery')
	const enableProjectDeliveryApproval = false
	const showApprovalFlow = computed(() => enableProjectDeliveryApproval && isOpenProcess.value)
	if (showApprovalFlow.value) {
		formRules.approveUserIdList = [required('审批人不能为空')]
	}

	const warehousesList = ref([])
	const freightCategoryOptions = ref([])
	const logisticsCategory = ref([])
	const activeKey = ref('')
	const approveFormRef = ref()
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
			dataIndex: 'amount'
		},
		{
			title: '发货仓库',
			width: '25%',
			dataIndex: 'warehousesId'
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
	const allProductList = ref([])
	const error = ref(false)
	const loading = ref(false)
	let id = ''
	const createRequestId = () => {
		if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
			return crypto.randomUUID()
		}
		return `${Date.now()}-${Math.random().toString(36).slice(2)}`
	}
	// 打开抽屉
	const onOpen = async (record) => {
		visible.value = true
		formData.value = {
			projectId: record.id,
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList,
			projectProductItemList: [],
			requestId: createRequestId(),
			freightTime: dayjs().format('YYYY-MM-DD HH:mm:ss')
		}

		activeKey.value = 'baseInfo'
		id = record.id

		await loadInitData()
		freightCategoryOptions.value = tool.dictList('FREIGHT_CATEGORY')
		logisticsCategory.value = tool.dictList('LOGISTICS_CATEGORY')
	}

	const defaultWarehouseId = ref('')
	const loadInitData = async () => {
		try {
			error.value = false
			loading.value = true
			defaultWarehouseId.value = ''
			let warehoues = await warehousesApi.warehousesList()
			await loadingTreeData()

			warehousesList.value = warehoues.map((v) => {
				return {
					label: v.name,
					value: v.id
				}
			})

			let res = await bizSaleProjectApi.bizSaleProjectProductItemList({ id: id })
			allProductList.value = res
			const { bizSaleProject } = await bizSaleProjectApi.bizSaleProjectDetail({ id: id })
			const find = findWarehouseDeptId(treeData.value, warehoues, bizSaleProject.org)
			defaultWarehouseId.value = find ? find.id : ''
			formData.value.consignee = bizSaleProject.consignee
			formData.value.phone = bizSaleProject.phone
			formData.value.unit = bizSaleProject.unit
			formData.value.address = bizSaleProject.address
			formData.value.freightCategory = bizSaleProject.freightCategory
			formData.value.freight = bizSaleProject.freight
		} catch (e) {
			console.log(e)
			error.value = true
		} finally {
			loading.value = false
		}
	}

	// 关闭抽屉
	const onClose = () => {
		emit('successful')
		visible.value = false
	}

	// 站内信分类字典

	// 验证并提交数据
	const onSubmit = async () => {
		if (sendLoading.value) return
		sendLoading.value = true
		try {
			try {
				await formRef.value.validate()
			} catch (e) {
				activeKey.value = 'baseInfo'
				return
			}

			try {
				await productFormRef.value.validate()
			} catch (e) {
				activeKey.value = 'product'
				return
			}

			if (showApprovalFlow.value) {
				try {
					await approveFormRef.value.validate()
				} catch (e) {
					activeKey.value = 'approve-info'
					return
				}
			}

			await bizProcessApi.bizProcessStartProjectDelivery(cloneDeep(formData.value))
			onClose()
		} finally {
			sendLoading.value = false
		}
	}
	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()
	// 列表选择配置
	const modalColumn = ref([
		{
			title: '产品名称',
			dataIndex: 'productName',
			width: '20%'
		},
		{
			title: '产品规格',
			dataIndex: 'productSpecs'
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

	const modalProductList = computed(() => {
		const list = allProductList.value.filter((v) => {
			return (
				(v.state === 'WAIT_DELIVER' || v.state === 'PART_WAIT_DELIVER') &&
				formData.value.projectProductItemList.every((p) => p.projectProductItemId != v.id)
			)
		})
		return list
	})
	let currentSelect = []

	const rowSelection = ref({
		onChange: (selectedRowKey, selectedRows) => {
			currentSelect = selectedRows
		},
		getCheckboxProps: (record) => ({
			disabled: record.objectId
		})
	})

	const showSelect = ref(false)
	const openSelect = async () => {
		showSelect.value = true
	}
	const onSelect = () => {
		let arr = currentSelect.map((v) => {
			let max = v.number - v.delivery
			return {
				projectProductItemId: v.id,
				productCategory: v.productCategory,
				warehousesId: defaultWarehouseId.value
					? defaultWarehouseId.value
					: warehousesList.value.length
					  ? warehousesList.value[0].value
					  : '',
				productName: v.productName,
				amount: v.number - v.delivery,
				productId: v.productId,
				remark: '',
				max: max
			}
		})

		formData.value.projectProductItemList.push(...arr)

		showSelect.value = false
	}

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
