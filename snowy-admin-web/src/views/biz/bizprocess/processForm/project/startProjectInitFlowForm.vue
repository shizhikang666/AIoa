<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="申请审批"
		:width="1050"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton :loading="loading" active>
			<a-tabs v-model:activeKey="activeKey">
				<a-tab-pane key="info" tab="订单信息">
					<a-form ref="InfoRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="收款账户：" name="accountId">
							<a-select
								placeholder="请选择收款账户"
								v-model:value="formData.accountId"
								:options="accountList"
							></a-select>
						</a-form-item>
						<a-form-item label="收款方式：" name="payerCategory">
							<a-select
								placeholder="请选择收款方式"
								v-model:value="formData.payerCategory"
								:options="payerCategoryOptions"
							></a-select>
						</a-form-item>
						<a-form-item label="回扣金额：" name="rebateAmount">
							<XnCurrencyInput v-model:value="formData.rebateAmount" placeholder="请输入回扣" style="width: 100%" />
						</a-form-item>
						<a-form-item label="计划出差天数：" name="travelDays">
							<a-input-number
								v-model:value="formData.travelDays"
								:min="0"
								:max="3650"
								:step="0.5"
								:precision="1"
								placeholder="0表示无需出差"
								style="width: 100%"
							/>
						</a-form-item>
						<!--					<a-form-item label="成交日期：" name="completionDate">-->
						<!--						<a-date-picker-->
						<!--							placeholder="请选择收款方式"-->
						<!--							v-model:value="formData.completionDate"-->
						<!--							value-format="YYYY-MM-DD HH:mm:ss"-->
						<!--						></a-date-picker>-->
						<!--					</a-form-item>-->
						<a-form-item label="订单备注：" name="remark">
							<a-textarea
								v-model:value="formData.remark"
								placeholder="请输入备注"
								:auto-size="{ minRows: 5, maxRows: 5 }"
							/>
						</a-form-item>
					</a-form>
				</a-tab-pane>
				<a-tab-pane :forceRender="true" key="productInfo" tab="订单产品">
					<a-form class="product-form" ref="productFormRef" :model="formData" layout="vertical">
						<a-form-item
							:key="formData.productList"
							style="margin-bottom: 0"
							:name="'productList'"
							:rules="{ required: true, message: '发货数量必填' }"
						>
							<a-button class="editable-add-btn" style="margin-bottom: 8px" @click="handleAdd">添加表单 </a-button>
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
									<a-button @click="removeItem(record)" type="link" danger size="small">删除 </a-button>
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
				<a-tab-pane :forceRender="true" key="deliveryPlan" tab="发货安排">
					<a-alert
						message="一个发货安排生成一张发货单；同一地址需要分批发货时，请拆成多个安排。"
						type="info"
						show-icon
						style="margin-bottom: 16px"
					/>
					<a-empty v-if="!formData.productList?.length" description="请先在“订单产品”中添加产品" />
					<template v-else>
						<a-card
							v-for="(plan, planIndex) in formData.deliveryPlanList"
							:key="plan.clientKey || plan.id || planIndex"
							size="small"
							:style="{ marginBottom: '16px' }"
						>
							<template #title>发货安排 {{ planIndex + 1 }}</template>
							<template #extra>
								<a-space>
									<a-button type="link" size="small" @click="copyDeliveryPlan(plan)">复制此安排</a-button>
									<a-popconfirm
										title="确认删除这个发货安排？"
										:disabled="formData.deliveryPlanList.length === 1"
										@confirm="removeDeliveryPlan(planIndex)"
									>
										<a-button type="link" danger size="small" :disabled="formData.deliveryPlanList.length === 1">
											删除
										</a-button>
									</a-popconfirm>
								</a-space>
							</template>

							<a-row :gutter="16">
								<a-col :span="12">
									<a-form-item label="收货单位" required>
										<a-input v-model:value="plan.unit" :maxlength="100" placeholder="请输入收货单位" />
									</a-form-item>
								</a-col>
								<a-col :span="12">
									<a-form-item label="收货人" required>
										<a-input v-model:value="plan.consignee" :maxlength="40" placeholder="请输入收货人" />
									</a-form-item>
								</a-col>
								<a-col :span="12">
									<a-form-item label="联系电话" required>
										<a-input v-model:value="plan.phone" :maxlength="40" placeholder="请输入联系电话" />
									</a-form-item>
								</a-col>
								<a-col :span="12">
									<a-form-item label="收货地址" required>
										<a-input v-model:value="plan.address" :maxlength="100" placeholder="请输入收货地址" />
									</a-form-item>
								</a-col>
								<a-col :span="8">
									<a-form-item label="运费支付方式">
										<a-select
											v-model:value="plan.freightCategory"
											:options="freightCategoryOptions"
											placeholder="请选择"
											allow-clear
										/>
									</a-form-item>
								</a-col>
								<a-col :span="8">
									<a-form-item label="运费金额">
										<a-input-number
											v-model:value="plan.freight"
											:min="0"
											:precision="2"
											prefix="￥"
											placeholder="可在实际发货时填写"
											style="width: 100%"
										/>
									</a-form-item>
								</a-col>
								<a-col :span="8">
									<a-form-item label="指定物流类型">
										<a-select
											v-model:value="plan.logisticsCategory"
											:options="logisticsCategory"
											placeholder="可在实际发货时确定"
											allow-clear
										/>
									</a-form-item>
								</a-col>
								<a-col :span="24">
									<a-form-item label="安排备注">
										<a-textarea
											v-model:value="plan.remark"
											:maxlength="4000"
											:auto-size="{ minRows: 2, maxRows: 4 }"
										/>
									</a-form-item>
								</a-col>
							</a-row>

							<a-table
								row-key="productId"
								size="small"
								bordered
								:pagination="false"
								:columns="deliveryPlanColumns"
								:data-source="plan.productList"
							>
								<template #bodyCell="{ column, record }">
									<template v-if="column.dataIndex === 'amount'">
										<a-input-number
											v-model:value="record.amount"
											:min="0"
											:max="record.orderNumber"
											:precision="0"
											style="width: 100%"
										/>
									</template>
									<template v-if="column.dataIndex === 'allocated'">
										<span :class="allocationClass(record)">
											{{ allocatedQuantity(record.productId) }} / {{ record.orderNumber }}
										</span>
									</template>
									<template v-if="column.dataIndex === 'remark'">
										<a-input v-model:value="record.remark" :maxlength="100" placeholder="选填" />
									</template>
								</template>
							</a-table>
						</a-card>
						<a-button type="dashed" block @click="addDeliveryPlan">+ 新增发货安排</a-button>
					</template>
				</a-tab-pane>
				<a-tab-pane tab="合同信息" key="file-list">
					<a-form class="product-form" ref="fileFormRef" :model="formData" layout="vertical">
						<a-space>
							<a-form-item
								key="fileIdList"
								style="margin-bottom: 0"
								:name="'fileIdList'"
								:rules="{ required: true, message: '附件信息不能为空' }"
							>
								<a-button type="primary" @click="() => uploadFormRef.openUpload()">
									<UploadOutlined />
									文件上传
								</a-button>
							</a-form-item>
						</a-space>

						<a-list item-layout="horizontal" :data-source="list">
							<template #renderItem="{ item, index }">
								<a-list-item key="item.id">
									<FileViewItem :item="item" @remove="list.splice(index, 1)"></FileViewItem>
								</a-list-item>
							</template>
						</a-list>
					</a-form>
				</a-tab-pane>
				<a-tab-pane tab="开票信息" key="invoiceInfo">
					<a-form ref="isInvoicingRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="是否需要开票：" name="isInvoicing">
							<a-radio-group v-model:value="formData.isInvoicing">
								<a-radio :value="false">无需开票</a-radio>
								<a-radio :value="true">需要开票</a-radio>
							</a-radio-group>
						</a-form-item>
					</a-form>
					<a-form
						ref="invoicingInfoRef"
						v-if="formData.isInvoicing"
						:model="formData.invoicingInfo"
						:rules="invoiceRules"
						layout="vertical"
					>
						<a-form-item label="开票公司：" name="companyName">
							<a-select
								v-model:value="formData.invoicingInfo.companyName"
								show-search
								:filter-option="filterOption"
								placeholder="请选择开票公司"
								:options="invoiceCompanyOptions"
								allow-clear
							/>
						</a-form-item>
						<a-form-item label="开票类型：" name="invoicingCategory">
							<a-select
								v-model:value="formData.invoicingInfo.invoicingCategory"
								placeholder="请选择分类"
								:options="invoiceCategoryOptions"
								@change="onInvoicingCategoryChange"
							/>
						</a-form-item>

						<a-form-item label="开票金额：" name="amount">
							<XnCurrencyInput :min="0" v-model:value="formData.invoicingInfo.amount" placeholder="请输入金额" />
						</a-form-item>
						<a-form-item label="客户单位名称：" name="customerCompany">
							<a-input
								v-model:value="formData.invoicingInfo.customerCompany"
								placeholder="请输入客户公司"
								allow-clear
							/>
						</a-form-item>
						<a-form-item v-if="!isGeneralInvoice" label="单位全称：" name="unit">
							<a-input v-model:value="formData.invoicingInfo.unit" placeholder="请输入单位全称" allow-clear />
						</a-form-item>
						<a-form-item v-if="!isGeneralInvoice" label="单位地址：" name="unitAddress">
							<a-input v-model:value="formData.invoicingInfo.unitAddress" placeholder="请输入单位地址" allow-clear />
						</a-form-item>
						<a-form-item v-if="!isGeneralInvoice" label="单位电话：" name="unitPhone">
							<a-input v-model:value="formData.invoicingInfo.unitPhone" placeholder="请输入单位电话" allow-clear />
						</a-form-item>

						<a-form-item label="纳税人号：" name="taxpayer">
							<a-input v-model:value="formData.invoicingInfo.taxpayer" placeholder="请输入纳税人号" allow-clear />
						</a-form-item>

						<a-form-item v-if="!isGeneralInvoice" label="开户银行：" name="bankName">
							<a-input v-model:value="formData.invoicingInfo.bankName" placeholder="请输入开户银行" allow-clear />
						</a-form-item>
						<a-form-item v-if="!isGeneralInvoice" label="开户银行账户：" name="corporateAccount">
							<a-input
								v-model:value="formData.invoicingInfo.corporateAccount"
								placeholder="请输入对公账户"
								allow-clear
							/>
						</a-form-item>
						<a-form-item v-if="!isGeneralInvoice" label="发票收货联系电话：" name="phone">
							<a-input v-model:value="formData.invoicingInfo.phone" placeholder="请输入发票收货联系电话" allow-clear />
						</a-form-item>

						<a-form-item v-if="!isGeneralInvoice" label="发票收货地址：" name="harvestAddress">
							<a-input v-model:value="formData.invoicingInfo.harvestAddress" placeholder="请输入发票地址" allow-clear />
						</a-form-item>

						<a-form-item v-if="!isGeneralInvoice" label="备注：" name="remark">
							<a-textarea v-model:value="formData.invoicingInfo.remark" placeholder="请输入备注" allow-clear />
						</a-form-item>
					</a-form>
				</a-tab-pane>
				<a-tab-pane key="approval" tab="审批信息" v-if="showApprovalFlow">
					<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="审批人：" name="approveUserIdList">
							<xn-user-selector
								:org-tree-api="selectorApiFunction.orgTreeApi"
								:user-page-api="selectorApiFunction.userPageApi"
								:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
								data-type="object"
								v-model:value="formData.approveUserIdList"
							/>
						</a-form-item>
						<a-form-item v-if="showApprovalFlow" label="抄送人：" name="receiverIdList">
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
			<a-row justify="end">
				<a-col style="margin-right: 8px">
					<a-form ref="initPriceFormRef" :model="formData" :rules="formRules">
						<a-form-item label="订单金额：" required name="initPrice">
							<xn-currency-input
								v-model:value="formData.initPrice"
								placeholder="请输入订单初始金额"
							></xn-currency-input>
						</a-form-item>
					</a-form>
				</a-col>
				<a-col>
					<a-button class="xn-mr8" @click="onClose">关闭</a-button>
					<a-button class="xn-mr8" :loading="loadingSaveDraft" @click="saveDraft">保存为草稿</a-button>
					<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
				</a-col>
			</a-row>
		</template>
	</xn-form-container>
	<uploadForm ref="uploadFormRef" @successful="onUploadSuccess" />
</template>

<script setup name="startFlowForm">
	import { required } from '@/utils/formRules'
	import { App, message } from 'ant-design-vue'

	const { modal } = App.useApp()
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import userApi from '@/api/sys/userApi'
	import userCenterApi from '@/api/sys/userCenterApi'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useSelectFilterOption } from '@/composables/useSelectFilterOption'
	import { computed, createVNode, ref } from 'vue'
	import SelectProductModal from '@/views/biz/bizproduct/modal/selectProductModal/index.vue'
	import { Decimal } from 'decimal.js'
	import SettlementAccountApi from '@/api/biz/settlementAccountApi'
	import { openFilePreview } from '@/utils/filePreview'
	import UploadForm from '@/views/biz/file/uploadForm.vue'

	import bizDraftApi from '@/api/biz/bizDraftApi'
	import { useLoading } from '@/composables/useLoading'
	import dayjs from '@/utils/dayjs/index'
	import { useProduct } from '@/composables/useProduct'
	import BizSaleProjectInvoicingApi from '@/api/biz/bizSaleProjectInvoicingApi'
	import FileViewItem from '@/components/File/FileViewItem.vue'
	import { safeJsonParse } from '@/utils/json'

	const list = ref([])
	const onUploadSuccess = (res) => {
		list.value.push(res)
	}

	const uploadFormRef = ref()
	const freightCategoryOptions = ref(tool.dictList('FREIGHT_CATEGORY'))
	const invoiceCategoryOptions = ref(tool.dictList('InvoicingCategory'))
	const activeKey = ref()
	const payerCategoryOptions = ref([])
	payerCategoryOptions.value = tool.dictListByPath(['SALE_PROJECT', 'payerCategory'])

	const accountList = ref([])
	const invoiceCompanyOptions = ref([])

	const sendLoading = ref(false)
	// 定义emit事件
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const showApprovalFlow = false
	const generalInvoiceCategory = 'GeneralTicket'
	const specialInvoiceFieldKeys = [
		'unit',
		'unitAddress',
		'unitPhone',
		'bankName',
		'corporateAccount',
		'phone',
		'harvestAddress',
		'remark'
	]
	const isGeneralInvoice = computed(() => formData.value.invoicingInfo?.invoicingCategory === generalInvoiceCategory)
	const fileFormRef = ref()
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
			title: '指导价',
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
	const deliveryPlanColumns = [
		{
			title: '产品名称',
			dataIndex: 'productName'
		},
		{
			title: '订单数量',
			dataIndex: 'orderNumber',
			width: 100
		},
		{
			title: '本安排数量',
			dataIndex: 'amount',
			width: 130
		},
		{
			title: '已安排 / 订单',
			dataIndex: 'allocated',
			width: 130
		},
		{
			title: '备注',
			dataIndex: 'remark'
		}
	]

	const { warpProduct } = useProduct()
	const createDeliveryPlanKey = () => {
		if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
			return crypto.randomUUID()
		}
		return `${Date.now()}-${Math.random().toString(36).slice(2)}`
	}
	const deliveryPlanItems = (plan) => {
		if (Array.isArray(plan?.productList)) return plan.productList
		if (Array.isArray(plan?.productItemList)) return plan.productItemList
		if (Array.isArray(plan?.itemList)) return plan.itemList
		if (Array.isArray(plan?.items)) return plan.items
		if (Array.isArray(plan?.projectProductItemList)) return plan.projectProductItemList
		return []
	}
	const emptyDeliveryPlan = (source = {}) => ({
		...source,
		clientKey: source.clientKey || createDeliveryPlanKey(),
		unit: source.unit || '',
		consignee: source.consignee || '',
		phone: source.phone || '',
		address: source.address || '',
		freightCategory: source.freightCategory || '',
		freight: source.freight ?? null,
		logisticsCategory: source.logisticsCategory || undefined,
		remark: source.remark || '',
		productList: deliveryPlanItems(source)
	})
	const syncDeliveryPlanProducts = (forceSinglePlan = false) => {
		const products = Array.isArray(formData.value.productList) ? formData.value.productList : []
		if (!Array.isArray(formData.value.deliveryPlanList) || formData.value.deliveryPlanList.length === 0) {
			formData.value.deliveryPlanList = [emptyDeliveryPlan()]
		}
		const singlePlan = formData.value.deliveryPlanList.length === 1
		formData.value.deliveryPlanList.forEach((plan) => {
			const oldItems = new Map(
				deliveryPlanItems(plan).map((item) => [String(item.productId || item.id || ''), item])
			)
			plan.productList = products.map((product) => {
				const productId = String(product.productId || product.id || '')
				const oldItem = oldItems.get(productId) || {}
				const orderNumber = Number(product.number || 0)
				let amount = oldItem.amount ?? oldItem.number
				if (amount === undefined || amount === null || amount === '') {
					amount = singlePlan ? orderNumber : 0
				}
				if (singlePlan && forceSinglePlan) {
					amount = orderNumber
				}
				return {
					...oldItem,
					productId,
					projectProductItemId: oldItem.projectProductItemId || product.projectProductItemId || undefined,
					productName: product.productName,
					orderNumber,
					amount: Number(amount || 0),
					remark: oldItem.remark || ''
				}
			})
		})
	}
	const normalizeDeliveryPlans = () => {
		const existingPlans = Array.isArray(formData.value.deliveryPlanList) ? formData.value.deliveryPlanList : []
		if (existingPlans.length > 0) {
			formData.value.deliveryPlanList = existingPlans.map((plan) => emptyDeliveryPlan(plan))
		} else {
			formData.value.deliveryPlanList = [
				emptyDeliveryPlan({
					unit: formData.value.unit,
					consignee: formData.value.consignee,
					phone: formData.value.phone,
					address: formData.value.address,
					freightCategory: formData.value.freightCategory,
					freight: formData.value.freight ?? null,
					logisticsCategory: formData.value.logisticsCategory,
					remark: formData.value.deliveryNote || ''
				})
			]
		}
		syncDeliveryPlanProducts(existingPlans.length === 0)
	}
	const addDeliveryPlan = () => {
		if (formData.value.deliveryPlanList.length >= 50) {
			message.warning('一个项目最多添加50个发货安排')
			return
		}
		formData.value.deliveryPlanList.push(emptyDeliveryPlan())
		syncDeliveryPlanProducts()
	}
	const copyDeliveryPlan = (source) => {
		if (formData.value.deliveryPlanList.length >= 50) {
			message.warning('一个项目最多添加50个发货安排')
			return
		}
		formData.value.deliveryPlanList.push(
			emptyDeliveryPlan({
				unit: source.unit,
				consignee: source.consignee,
				phone: source.phone,
				address: source.address,
				freightCategory: source.freightCategory,
				freight: source.freight,
				logisticsCategory: source.logisticsCategory,
				remark: source.remark
			})
		)
		syncDeliveryPlanProducts()
	}
	const removeDeliveryPlan = (index) => {
		if (formData.value.deliveryPlanList.length <= 1) return
		formData.value.deliveryPlanList.splice(index, 1)
		if (formData.value.deliveryPlanList.length === 1) {
			syncDeliveryPlanProducts(true)
		}
	}
	const allocatedQuantity = (productId) => {
		return (formData.value.deliveryPlanList || [])
			.reduce((total, plan) => {
				const item = (plan.productList || []).find((row) => String(row.productId) === String(productId))
				return total.plus(new Decimal(item?.amount || 0))
			}, new Decimal(0))
			.toString()
	}
	const allocationClass = (record) => {
		return new Decimal(allocatedQuantity(record.productId)).equals(new Decimal(record.orderNumber || 0))
			? 'allocation-ok'
			: 'allocation-error'
	}

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
		syncDeliveryPlanProducts()
	}

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
			const result = modelValue.value.map((item, index) => {
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
			formData.value.productList[index].price = price.minus(price.times(discount)).toString()
		}
		if (formData.value.deliveryPlanList?.length === 1) {
			syncDeliveryPlanProducts(true)
		} else {
			syncDeliveryPlanProducts()
		}
	}
	const totalPrice = computed(() => {
		return formData.value.productList
			.reduce((sum, item) => {
				return sum.plus(new Decimal(item.price ? item.price : 0))
			}, new Decimal(0))
			.toNumber()
	})

	const logisticsCategory = ref([])
	const filterOption = useSelectFilterOption()
	const clearSpecialInvoiceFields = () => {
		specialInvoiceFieldKeys.forEach((key) => {
			formData.value.invoicingInfo[key] = ''
		})
	}
	const onInvoicingCategoryChange = (value) => {
		if (value === generalInvoiceCategory) {
			clearSpecialInvoiceFields()
		}
	}
	// 打开抽屉

	const { load: onOpen, loading } = useLoading(async (record) => {
		visible.value = true
		list.value = []
		activeKey.value = 'info'
		const accountListRes = await SettlementAccountApi.settlementAccountList()
		logisticsCategory.value = tool.dictList('LOGISTICS_CATEGORY')
		const baseForm = await bizDraftApi.bizDraftDetail({
			id: record.id
		})
		accountList.value = accountListRes.map((v) => {
			return {
				label: v.accountName,
				value: v.id
			}
		})
		const accountNames = Array.from(
			new Set(accountListRes.map((v) => String(v.accountName || '').trim()).filter(Boolean))
		)
		invoiceCompanyOptions.value = accountNames.map((accountName) => {
			return {
				label: accountName,
				value: accountName
			}
		})
		formData.value = {
			rebateAmount: 0,
			travelDays: 0,
			invoicingInfo: {
				customerCompany: record.customerName
			},
			productList: [],
			deliveryPlanList: [],
			bizSaleProjectId: record.id,
			copyUserIdList: [],
			approveUserIdList: []
		}
		if (baseForm && baseForm.extJson) {
			const json = safeJsonParse(baseForm.extJson, {})

			list.value = json.fileList ? json.fileList : []
			const form = json.form || {}
			formData.value = Object.assign(formData.value, form)
		} else {
			let detail = await BizSaleProjectInvoicingApi.bizSaleProjectInvoicingQueryCustomer({
				id: record.customer
			})
			if (detail) {
				formData.value.invoicingInfo = { ...detail }
				formData.value.invoicingInfo.amount = ''
			}
		}
		if (formData.value.invoicingInfo?.invoicingCategory === generalInvoiceCategory) {
			clearSpecialInvoiceFields()
		}
		normalizeDeliveryPlans()
		updateFormData()
	})

	// 关闭抽屉
	const onClose = () => {
		emit('successful')
		visible.value = false
	}
	// 默认要校验的
	const validateTravelDays = async (_rule, value) => {
		const days = Number(value)
		if (!Number.isFinite(days) || days < 0 || days > 3650 || !Number.isInteger(days * 2)) {
			return Promise.reject('计划出差天数只能按0.5天填写')
		}
		return Promise.resolve()
	}
	const formRules = {
		// completionDate: [required('成交日期不能为空')],
		rebateAmount: [required('回扣金额不能为空')],
		travelDays: [required('计划出差天数不能为空'), { validator: validateTravelDays, trigger: ['blur', 'change'] }],
		accountId: [required('收款账户不能为空')],
		// consignee: [required('收货人不能为空')],
		payerCategory: [required('收款方式不能为空')],
		// unit: [required('收货单位不能为空')],
		// address: [required('收货地址不能为空')],
		// phone: [required('收货人联系方式不能为空')],
		// freightCategory: [required('运费支付方式不能为空')],
		// freight: [required('运费金额不能为空')],
		isInvoicing: [required('请选择是否开票')]
	}
	const baseInvoiceRules = {
		amount: [required('请输入开票金额')],
		invoicingCategory: [required('请输入开票类型')],
		companyName: [required('请选择开票公司')],
		customerCompany: [required('请输入客户公司')],
		taxpayer: [required('请输入纳税人号')]
	}
	const specialInvoiceRules = {
		unit: [required('请输入单位全称')],
		corporateAccount: [required('请输入对公账户')],
		bankName: [required('请输入开户银行')],
		unitAddress: [required('请输入单位地址')],
		unitPhone: [required('请输入单位电话')]
	}
	const invoiceRules = computed(() => {
		return isGeneralInvoice.value ? baseInvoiceRules : { ...baseInvoiceRules, ...specialInvoiceRules }
	})

	if (showApprovalFlow) {
		formRules['approveUserIdList'] = [required('请选择审批人')]
	}
	const InfoRef = ref()
	const initPriceFormRef = ref()

	const isInvoicingRef = ref()

	const invoicingInfoRef = ref()
	const isBlankFreight = (value) => value === '' || value === null || value === undefined
	const validateDeliveryPlans = () => {
		const plans = formData.value.deliveryPlanList || []
		if (plans.length === 0) {
			message.warning('请至少添加一个发货安排')
			return false
		}
		let totalItemCount = 0
		for (let index = 0; index < plans.length; index += 1) {
			const plan = plans[index]
			const planName = `发货安排 ${index + 1}`
			for (const [key, label] of [
				['unit', '收货单位'],
				['consignee', '收货人'],
				['phone', '联系电话'],
				['address', '收货地址']
			]) {
				if (!String(plan[key] || '').trim()) {
					message.warning(`${planName}：${label}不能为空`)
					return false
				}
			}
			const freight = Number(plan.freight)
			if (
				!isBlankFreight(plan.freight) &&
				(!Number.isFinite(freight) || freight < 0)
			) {
				message.warning(`${planName}：运费金额不能小于0`)
				return false
			}
			const positiveItems = (plan.productList || []).filter((item) => Number(item.amount) > 0)
			totalItemCount += positiveItems.length
			if (positiveItems.length === 0) {
				message.warning(`${planName}：请至少安排一个产品`)
				return false
			}
			if (
				positiveItems.some((item) => !Number.isFinite(Number(item.amount)) || !Number.isInteger(Number(item.amount)))
			) {
				message.warning(`${planName}：产品数量必须为正整数`)
				return false
			}
		}
		if (totalItemCount > 500) {
			message.warning('发货安排中的产品明细不能超过500条')
			return false
		}

		for (const product of formData.value.productList || []) {
			const allocated = new Decimal(allocatedQuantity(product.productId))
			const ordered = new Decimal(product.number || 0)
			if (!allocated.equals(ordered)) {
				message.warning(`${product.productName}：已安排 ${allocated.toString()}，订单数量 ${ordered.toString()}`)
				return false
			}
		}
		return true
	}
	const deliveryPlanPayload = (form) => {
		const plans = (form.deliveryPlanList || []).map((plan, index) => ({
			...(plan.id ? { id: plan.id } : {}),
			planNo: index + 1,
			unit: String(plan.unit || '').trim(),
			consignee: String(plan.consignee || '').trim(),
			phone: String(plan.phone || '').trim(),
			address: String(plan.address || '').trim(),
			freightCategory: plan.freightCategory,
			freight: isBlankFreight(plan.freight) ? null : new Decimal(plan.freight).toFixed(2),
			logisticsCategory: plan.logisticsCategory || '',
			remark: String(plan.remark || '').trim(),
			productList: (plan.productList || [])
				.filter((item) => Number(item.amount) > 0)
				.map((item) => ({
					productId: item.productId,
					...(item.projectProductItemId ? { projectProductItemId: item.projectProductItemId } : {}),
					amount: Number(item.amount),
					remark: String(item.remark || '').trim()
				}))
		}))
		const firstPlan = plans[0]
		form.deliveryPlanList = plans
		form.unit = firstPlan.unit
		form.consignee = firstPlan.consignee
		form.phone = firstPlan.phone
		form.address = firstPlan.address
		form.freightCategory = firstPlan.freightCategory
		form.logisticsCategory = firstPlan.logisticsCategory
		const freightList = plans.map((plan) => plan.freight).filter((freight) => !isBlankFreight(freight))
		form.freight = freightList.length
			? freightList.reduce((total, freight) => total.plus(new Decimal(freight)), new Decimal(0)).toFixed(2)
			: null
		return form
	}
	const { load: saveDraft, loading: loadingSaveDraft } = useLoading(async () => {
		let form = cloneDeep(formData.value)
		await bizDraftApi.bizDraftSubmitSaleProjectForm({
			targetId: form.bizSaleProjectId,
			extJson: JSON.stringify({
				form: form,
				fileList: list.value
			})
		})
	})
	// 验证并提交数据
	const onSubmit = async () => {
		formData.value.fileIdList = list.value.map((v) => v.id)
		try {
			await InfoRef.value.validate()
		} catch (e) {
			activeKey.value = 'info'
			return
		}
		if (activeKey.value === 'info') {
			activeKey.value = 'productInfo'
			return
		}

		try {
			await productFormRef.value.validate()
		} catch (e) {
			activeKey.value = 'productInfo'
			return
		}

		if (activeKey.value === 'productInfo') {
			activeKey.value = 'deliveryPlan'
			return
		}

		if (!validateDeliveryPlans()) {
			activeKey.value = 'deliveryPlan'
			return
		}
		if (activeKey.value === 'deliveryPlan') {
			activeKey.value = 'file-list'
			return
		}

		try {
			await fileFormRef.value.validate()
		} catch (e) {
			activeKey.value = 'file-list'
			return
		}
		if (activeKey.value === 'file-list') {
			activeKey.value = 'invoiceInfo'
			return
		}

		try {
			await isInvoicingRef.value.validate()
			if (formData.value.isInvoicing) {
				await invoicingInfoRef.value.validate()
			}
		} catch (e) {
			activeKey.value = 'invoiceInfo'
			return
		}

		if (showApprovalFlow) {
			try {
				await formRef.value.validate()
			} catch (e) {
				activeKey.value = 'approval'
				return
			}
		}

		try {
			await initPriceFormRef.value.validate()
		} catch (e) {
			return
		}

		sendLoading.value = true
		let form = deliveryPlanPayload(cloneDeep(formData.value))
		form.deliveryNote = form.remark

		try {
			saveDraft().then(() => {})
			await bizProcessApi.bizProcessStartProjectInit(form)
			onClose()
		} finally {
			sendLoading.value = false
		}
	}

	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()
	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		onOpen
	})
</script>
<style scoped>
	::v-deep(.product-form .ant-form-item) {
		margin-bottom: 0;
	}

	.allocation-ok {
		color: #389e0d;
	}

	.allocation-error {
		color: #cf1322;
		font-weight: 600;
	}
</style>
