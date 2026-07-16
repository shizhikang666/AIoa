<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		:title="formTitle"
		:width="1050"
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
			<a-alert
				v-if="!error && isPlanDelivery"
				message="本次将按所选发货安排生成一张发货单；收货信息和商品数量以成交时的安排为准，运费金额及支付方式可在发货时调整。"
				type="info"
				show-icon
				style="margin: 12px 0"
			/>
			<a-form-item v-if="!error && showShipmentModeSwitch" label="发货类型" style="margin-bottom: 8px">
				<a-radio-group v-model:value="shipmentMode" button-style="solid" @change="onShipmentModeChange">
					<a-radio-button value="PLAN">正常发货安排</a-radio-button>
					<a-radio-button value="REISSUE">补发</a-radio-button>
				</a-radio-group>
			</a-form-item>
			<a-tabs v-if="!error" v-model:activeKey="activeKey">
				<a-tab-pane :forceRender="true" key="baseInfo" tab="基本信息">
					<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item v-if="isPlanDelivery" label="发货安排" required>
							<a-select
								v-model:value="formData.deliveryPlanId"
								:options="deliveryPlanOptions"
								placeholder="请选择本次要执行的发货安排"
								@change="applyDeliveryPlan"
							/>
						</a-form-item>
						<a-form-item label="收货人：" name="consignee">
							<a-input :disabled="isPlanDelivery" placeholder="请输入收货人" v-model:value="formData.consignee"></a-input>
						</a-form-item>
						<a-form-item label="收货单位：" name="unit">
							<a-input :disabled="isPlanDelivery" placeholder="请输入收货单位" v-model:value="formData.unit"></a-input>
						</a-form-item>
						<a-form-item label="收货地址：" name="address">
							<a-input :disabled="isPlanDelivery" placeholder="请输入收货地址" v-model:value="formData.address"></a-input>
						</a-form-item>
						<a-form-item label="联系电话：" name="phone">
							<a-input :disabled="isPlanDelivery" placeholder="请输入收货人" v-model:value="formData.phone"></a-input>
						</a-form-item>
						<a-form-item label="运费支付方式：" name="freightCategory">
							<a-select
								placeholder="请选择运费支付方式"
								v-model:value="formData.freightCategory"
								:options="freightCategoryOptions"
							></a-select>
						</a-form-item>
						<a-form-item label="运费金额：" name="freight">
							<a-input-number
								:min="0"
								:precision="2"
								prefix="￥"
								v-model:value="formData.freight"
								placeholder="请输入运费金额"
								style="width: 100%"
							/>
						</a-form-item>
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
							<a-button
								v-if="!isPlanDelivery"
								class="editable-add-btn"
								style="margin-bottom: 8px"
								@click="openSelect"
							>
								添加表单
							</a-button>
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
								<template v-if="column.dataIndex === 'category'">
									<a-tag :color="record.category === 'REISSUE_ORDER' ? 'orange' : 'blue'">
										{{ shipmentCategoryText(record.category) }}
									</a-tag>
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
										:name="['projectProductItemList', index, 'amount']"
										:rules="{ required: true, message: '数量必填', trigger: 'change' }"
									>
										<a-input-number
											:disabled="isPlanDelivery"
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
									<a-button
										v-if="!isPlanDelivery"
										@click="formData.projectProductItemList.splice(index, 1)"
										type="link"
										danger
										size="small"
									>
										删除
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
						<a-tag :color="record.category === 'REISSUE_ORDER' ? 'orange' : 'blue'">
							{{ shipmentCategoryText(record.category) }}
						</a-tag>
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
	import { safeJsonParse } from '@/utils/json'

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
		freight: [required('运费金额必填')],
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
			title: '发货类型',
			dataIndex: 'category',
			width: '15%'
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
	const projectBaseInfo = ref({})
	const pendingDeliveryPlans = ref([])
	const shipmentMode = ref('LEGACY')
	const hasPendingReissue = ref(false)
	const reissueOnly = ref(false)
	const isPlanDelivery = computed(() => shipmentMode.value === 'PLAN' && pendingDeliveryPlans.value.length > 0)
	const showShipmentModeSwitch = computed(() => pendingDeliveryPlans.value.length > 0 && hasPendingReissue.value)
	const formTitle = computed(() => {
		if (isPlanDelivery.value) return '执行发货安排'
		return reissueOnly.value ? '添加补发记录' : '添加发货记录'
	})
	const shipmentCategoryText = (category) => (category === 'REISSUE_ORDER' ? '补发' : '正常发货')
	const deliveryPlanProductList = (plan) => {
		if (Array.isArray(plan?.productList)) return plan.productList
		if (Array.isArray(plan?.productItemList)) return plan.productItemList
		if (Array.isArray(plan?.itemList)) return plan.itemList
		if (Array.isArray(plan?.items)) return plan.items
		if (Array.isArray(plan?.projectProductItemList)) return plan.projectProductItemList
		const itemJson = plan?.itemJson ?? plan?.ITEM_JSON
		if (Array.isArray(itemJson)) return itemJson
		return safeJsonParse(itemJson, [])
	}
	const normalizeDeliveryPlanResponse = (result) => {
		const list = Array.isArray(result)
			? result
			: Array.isArray(result?.records)
			  ? result.records
			  : Array.isArray(result?.list)
			    ? result.list
			    : []
		return list.filter((plan) => {
			const status = String(plan.status || plan.STATUS || '')
			return !status || status === 'WAIT_DELIVER' || status === 'WAIT_SHIP'
		})
	}
	const deliveryPlanOptions = computed(() =>
		pendingDeliveryPlans.value.map((plan, index) => {
			const planNo = plan.planNo || plan.PLAN_NO || index + 1
			const address = plan.address || plan.ADDRESS || '未填写地址'
			const productCount = deliveryPlanProductList(plan).length
			const freight = plan.freight !== undefined ? plan.freight : plan.FREIGHT
			const freightText = freight === '' || freight === null || freight === undefined ? '运费待填写' : `运费¥${freight}`
			return {
				label: `安排 ${planNo}｜${address}｜${productCount}种物品｜${freightText}`,
				value: String(plan.id || plan.ID)
			}
		})
	)
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
		hasPendingReissue.value = Boolean(record.hasPendingReissue)
		reissueOnly.value = Boolean(record.hasPendingReissue && !record.hasPendingNormalShipment)
		shipmentMode.value = reissueOnly.value ? 'REISSUE' : 'LEGACY'
		pendingDeliveryPlans.value = []
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
	const applyProjectDefaults = () => {
		const project = projectBaseInfo.value
		formData.value.consignee = project.consignee
		formData.value.phone = project.phone
		formData.value.unit = project.unit
		formData.value.address = project.address
		formData.value.freightCategory = project.freightCategory
		formData.value.freight = project.freight ?? null
		formData.value.logisticsCategory = project.logisticsCategory || undefined
		formData.value.logisticsId = ''
		formData.value.remark = project.deliveryNote || ''
	}
	const applyDeliveryPlan = (planId) => {
		const plan = pendingDeliveryPlans.value.find((item) => String(item.id || item.ID) === String(planId))
		if (!plan) {
			formData.value.projectProductItemList = []
			return
		}
		formData.value.deliveryPlanId = String(plan.id || plan.ID)
		formData.value.consignee = plan.consignee ?? plan.CONSIGNEE ?? ''
		formData.value.phone = plan.phone ?? plan.PHONE ?? ''
		formData.value.unit = plan.unit ?? plan.UNIT ?? ''
		formData.value.address = plan.address ?? plan.ADDRESS ?? ''
		formData.value.freightCategory = plan.freightCategory ?? plan.FREIGHT_CATEGORY ?? ''
		const freight = plan.freight !== undefined ? plan.freight : plan.FREIGHT
		formData.value.freight = freight === '' || freight === undefined ? null : freight
		formData.value.logisticsCategory =
			plan.logisticsCategory ?? plan.LOGISTICS_CATEGORY ?? projectBaseInfo.value.logisticsCategory ?? undefined
		formData.value.logisticsId = ''
		formData.value.remark = plan.remark ?? plan.REMARK ?? ''
		formData.value.projectProductItemList = deliveryPlanProductList(plan).map((item) => {
			const projectItemId = item.projectProductItemId || item.PROJECT_PRODUCT_ITEM_ID || item.id
			const productId = item.productId || item.PRODUCT_ID
			const product = allProductList.value.find(
				(row) => String(row.id) === String(projectItemId) || String(row.productId) === String(productId)
			)
			const amount = Number(item.amount ?? item.AMOUNT ?? item.number ?? 0)
			return {
				projectProductItemId: projectItemId || product?.id,
				category: product?.category || item.category || 'INIT',
				productCategory: product?.productCategory || item.productCategory,
				warehousesId: defaultWarehouseId.value || warehousesList.value[0]?.value || '',
				productName: product?.productName || item.productName || '未找到产品信息',
				amount,
				productId: productId || product?.productId,
				remark: item.remark || '',
				max: amount
			}
		})
	}
	const onShipmentModeChange = () => {
		currentSelect = []
		formData.value.projectProductItemList = []
		if (shipmentMode.value === 'PLAN') {
			reissueOnly.value = false
			const firstPlanId = pendingDeliveryPlans.value[0]?.id || pendingDeliveryPlans.value[0]?.ID
			applyDeliveryPlan(firstPlanId)
			return
		}
		reissueOnly.value = shipmentMode.value === 'REISSUE'
		delete formData.value.deliveryPlanId
		applyProjectDefaults()
	}
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
			const detail = await bizSaleProjectApi.bizSaleProjectDetail({ id: id })
			const { bizSaleProject } = detail
			projectBaseInfo.value = bizSaleProject || {}
			const find = findWarehouseDeptId(treeData.value, warehoues, bizSaleProject.org)
			defaultWarehouseId.value = find ? find.id : ''
			applyProjectDefaults()

			let planResult = detail.deliveryPlanList || bizSaleProject.deliveryPlanList
			if (!Array.isArray(planResult)) {
				try {
					planResult = await bizSaleProjectApi.bizSaleProjectDeliveryPlanList({ projectId: id })
				} catch (planError) {
					console.warn('发货安排读取失败，按旧项目发货流程处理', planError)
					planResult = []
				}
			}
			pendingDeliveryPlans.value = normalizeDeliveryPlanResponse(planResult)
			hasPendingReissue.value =
				hasPendingReissue.value ||
				allProductList.value.some(
					(item) =>
						item.category === 'REISSUE_ORDER' &&
						(item.state === 'WAIT_DELIVER' || item.state === 'PART_WAIT_DELIVER')
				)
			const hasPendingNormal = allProductList.value.some(
				(item) =>
					item.category !== 'REISSUE_ORDER' &&
					(item.state === 'WAIT_DELIVER' || item.state === 'PART_WAIT_DELIVER')
			)
			if (pendingDeliveryPlans.value.length === 0 && !hasPendingNormal && hasPendingReissue.value) {
				reissueOnly.value = true
			}
			if (pendingDeliveryPlans.value.length > 0 && !reissueOnly.value) {
				shipmentMode.value = 'PLAN'
				applyDeliveryPlan(pendingDeliveryPlans.value[0].id || pendingDeliveryPlans.value[0].ID)
			} else {
				shipmentMode.value = reissueOnly.value ? 'REISSUE' : 'LEGACY'
			}
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
			if (isPlanDelivery.value && !formData.value.deliveryPlanId) {
				message.warning('请选择本次要执行的发货安排')
				activeKey.value = 'baseInfo'
				return
			}
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
				(reissueOnly.value ? v.category === 'REISSUE_ORDER' : v.category !== 'REISSUE_ORDER') &&
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
		if (isPlanDelivery.value) return
		currentSelect = []
		showSelect.value = true
	}
	const onSelect = () => {
		let arr = currentSelect.map((v) => {
			let max = v.number - v.delivery
			return {
				projectProductItemId: v.id,
				category: v.category,
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
