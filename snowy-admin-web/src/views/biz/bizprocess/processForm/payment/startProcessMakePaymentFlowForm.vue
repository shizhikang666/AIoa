<template>
	<xn-form-container
		:bodyStyle="{ paddingTop: 0 }"
		title="付款申请"
		:width="800"
		:visible="visible"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton active :loading="loading">
			<a-tabs v-model:activeKey="activeKey">
				<a-tab-pane tab="基本信息" key="baseInfo">
					<a-form
						:labelCol="{ span: 3 }"
						labelAlign="left"
						ref="formRef"
						:model="formData"
						:rules="formRules"
						layout="horizontal"
					>
						<a-form-item label="支出类型：" name="settlementCategory">
							<a-select
								placeholder="选择结算类型"
								v-model:value="formData.settlementCategory"
								:options="settlementCategoryList"
							></a-select>
						</a-form-item>
						<a-form-item v-if="formData.settlementCategory === 'GOODS_EXPENDITURE'" label="采购单：" name="objectId">
							<a-typography-link
								:type="formData.objectId ? '' : 'danger'"
								@click="openProcureFlowSelect('NOT_COMPLETED')"
							>
								{{
									activeSelectObject.id ? activeSelectObject.id + '(' + activeSelectObject.remark + ')' : '选择采购单'
								}}
							</a-typography-link>
						</a-form-item>
						<a-form-item v-if="formData.settlementCategory === 'ProcurementFreight'" label="采购单：" name="objectId">
							<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openProcureFlowSelect()">
								{{
									activeSelectObject.id ? activeSelectObject.id + '(' + activeSelectObject.remark + ')' : '选择采购单'
								}}
							</a-typography-link>
						</a-form-item>
						<a-form-item v-if="formData.settlementCategory === 'CUSTOMER_REBATE'" label="项目成交单：" name="objectId">
							<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openProjectSelect">
								{{
									activeSelectObject.id
										? activeSelectObject.id + '(' + activeSelectObject.projectName + ')'
										: '选择项目成交单'
								}}
							</a-typography-link>
						</a-form-item>

						<a-form-item v-if="formData.settlementCategory === 'repayment'" label="代收款单：" name="objectId">
							<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openCollectionReceipt">
								{{ activeSelectObject.id ? activeSelectObject.id + '(' + activeSelectObject.remark + ')' : '代收款单' }}
							</a-typography-link>
						</a-form-item>

						<a-form-item v-if="formData.settlementCategory === 'ReturnAndRefund'" label="退货单：" name="objectId">
							<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openReturnOrder">
								{{
									activeSelectObject.id ? activeSelectObject.id + '(' + activeSelectObject.projectName + ')' : '退货单'
								}}
							</a-typography-link>
						</a-form-item>
						<a-form-item v-if="formData.settlementCategory === 'TravelExpenses'" label="出差单：" name="objectId">
							<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openLeveApplication">
								{{
									activeSelectObject.id
										? `${activeSelectObject.startTime}- ${activeSelectObject.endTime}`
										: '请选择出差单'
								}}
							</a-typography-link>
						</a-form-item>

						<a-form-item label="收款人：" name="payer">
							<a-row>
								<a-space>
									<a-col span="12">
										<div>
											<a-select
												v-model:value="formData.payer"
												show-search
												:show-arrow="false"
												placeholder="请输入收款人"
												style="width: 200px"
												:not-found-content="state.fetching ? undefined : null"
												:options="supplierList"
												@change="changeSupplierName"
												@search="loadSupplierList"
											>
												<template v-if="state.fetching" #notFoundContent>
													<a-spin size="small" />
												</template>
											</a-select>
										</div>
									</a-col>
									<a-col>
										<a-button type="primary" @click="quickInput">使用本人收款信息</a-button>
									</a-col>
								</a-space>
							</a-row>
						</a-form-item>
						<a-form-item label="开户行：" name="bankName">
							<a-input placeholder="请输入开户行" v-model:value="formData.bankName"></a-input>
						</a-form-item>
						<a-form-item label="银行卡号：" name="bankAccount">
							<a-input
								@blur="formData.bankAccount ? (formData.bankAccount = formData.bankAccount.trim()) : ''"
								placeholder="请输入银行卡号"
								v-model:value="formData.bankAccount"
							></a-input>
						</a-form-item>
						<a-form-item label="金额：" name="amount">
							<XnCurrencyInput :min="0" v-model:value="formData.amount" placeholder="请输入收款金额" />
						</a-form-item>
						<a-form-item v-if="useSettlementAccount" label="预支款账户：" name="accountId">
							<a-row>
								<a-select
									show-search
									:filterOption="filterOption"
									placeholder="请选择收款账户"
									v-model:value="formData.accountId"
									:options="accountList"
								></a-select>
							</a-row>
						</a-form-item>
						<a-form-item v-if="useSettlementAccount" label="付款日期：" name="payerTime">
							<a-date-picker
								v-model:value="formData.payerTime"
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
						<!--					<a-form-item label="收款日期：" name="payerTime">-->
						<!--						<a-date-picker-->
						<!--							v-model:value="formData.payerTime"-->
						<!--							value-format="YYYY-MM-DD HH:mm:ss" show-time-->
						<!--						></a-date-picker>-->
						<!--					</a-form-item>-->
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
				<a-tab-pane tab="审核信息" key="approve-info">
					<a-form ref="approveFormRef" :model="formData" :rules="formRules" layout="vertical">
						<a-form-item label="财务：" name="treasurer">
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
	<uploadForm ref="uploadFormRef" @successful="onUploadSuccess" />
</template>
<script setup name="startProcessMakePaymentFlowForm">
	import { useLoading } from '@/composables/useLoading'
	import SettlementAccountApi from '@/api/biz/settlementAccountApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { required } from '@/utils/formRules'
	import { rules } from '@/utils/formRules'
	import tool from '@/utils/tool'
	import { useSelectFilterOption } from '@/composables/useSelectFilterOption'
	import { App } from 'ant-design-vue'
	import { createVNode, ref, useTemplateRef } from 'vue'
	import bizpurchaseorderModel from '@/views/biz/bizpurchaseorder/model/index.vue'
	import bizCollectionReceiptModel from '@/views/biz/bizcollectionreceipt/model/index.vue'
	import bizSaleProjectModal from '@/views/biz/saleproject/modal/index.vue'
	import returnOrderModel from '@/views/biz/returnorder/model/index.vue'
	import leaveApplicationModel from '@/views/biz/bizleaveapplication/modal/index.vue'

	import bizProcessApi from '@/api/biz/bizProcessApi'

	import supplierApi from '@/api/biz/supplierApi'
	import UploadForm from '@/views/biz/file/uploadForm.vue'
	import dayjs from 'dayjs'
	import zhCn from 'dayjs/locale/zh-cn'
	import relativeTime from 'dayjs/plugin/relativeTime'
	import { openFilePreview } from '@/utils/filePreview'
	import { cloneDeep, debounce } from 'lodash-es'
	import { useProcessParam } from '@/composables/useProcessParam'
	import { hasPerm } from '@/utils/permission/index'

	const { modal } = App.useApp()
	const visible = ref(false)
	const activeKey = ref('baseInfo')
	const formRef = useTemplateRef('formRef')
	const approveFormRef = useTemplateRef('approveFormRef')
	const uploadFormRef = useTemplateRef('uploadFormRef')
	import { globalStore } from '@/store'
	import { Decimal } from 'decimal.js'
	import bizPurchaseOrderApi from '@/api/biz/bizPurchaseOrderApi'
	import FileViewItem from '@/components/File/FileViewItem.vue'

	const store = globalStore()

	dayjs.extend(relativeTime)
	// 设置中文显示
	dayjs.locale(zhCn)

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

		formData.value.name = supplierList.value[0].label
		changeSupplierName(formData.value.name)
		state.fetching = false
	}, 300)

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
		formData.value.payer = name
		if (find) {
			// formData.value.supplier = Object.assign(formData.value.supplier, find)
			formData.value.bankName = find.bankName
			formData.value.bankAccount = find.bankAccount
		} else {
			formData.value.bankName = ''
			formData.value.bankAccount = ''
		}
	}

	const list = ref([])
	const formData = ref({})

	const settlementCategoryList = tool
		.dictListByPath(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY'])
		.filter((v) => {
			if (!hasPerm(v.value)) {
				return false
			}
			if (v.value === 'dealings') {
				return false
			}
			return true
		})
	const filterOption = useSelectFilterOption()
	const accountList = ref([])
	const selectorApiFunction = useUserSelector()
	const useSettlementAccount = ref(false)
	const activeSelectObject = ref({})
	const { loading, load: ObjectIdChangeLoad } = useLoading(async () => {
		if (formData.value.settlementCategory === 'GOODS_EXPENDITURE' && activeSelectObject.value.id) {
			const res = await bizPurchaseOrderApi.bizPurchaseOrderDetail({ id: activeSelectObject.value.id })
			let remark = activeSelectObject.value.remark + '\n'
			res.bizPurchaseOrderItemList.forEach((item) => {
				remark += `${item.productName} 数量 ${item.number} 金额${item.amount}\n`
			})
			formData.value.remark = remark

			let supplier = {}
			if (activeSelectObject.value.extJson) {
				supplier = JSON.parse(activeSelectObject.value.extJson).supplier
			}
			let otherSupplier = {}

			if (supplier.name) {
				const res = await supplierApi.supplierListQueryByName({
					name: supplier.name
				})
				if (res.length) {
					otherSupplier = res[0]
				}
			}

			formData.value.bankName = supplier.bankName ? supplier.bankName : otherSupplier.bankName
			formData.value.bankAccount = supplier.bankAccount ? supplier.bankAccount : otherSupplier.bankName
			formData.value.payer = supplier.name
			formData.value.amount = activeSelectObject.value.amount
		} else if (formData.value.settlementCategory === 'CUSTOMER_REBATE' && activeSelectObject.value.id) {
			formData.value.amount = activeSelectObject.value.rebateAmount
			formData.value.remark = `项目：${activeSelectObject.value.projectName} 客户：${activeSelectObject.value.customerName} 回扣 ${activeSelectObject.value.rebateAmount}`
		} else if (formData.value.settlementCategory === 'ReturnAndRefund' && activeSelectObject.value.id) {
			formData.value.remark = `项目：${activeSelectObject.value.projectName}退货支出`
		} else {
			formData.value.remark = ''
		}
	})
	SettlementAccountApi.settlementAccountList().then((res) => {
		accountList.value = res.map((v) => {
			return {
				label: v.accountName,
				value: v.id
			}
		})
	})
	const openProcureFlowSelect = (settlementStatus) => {
		let value = {}
		let content = createVNode(bizpurchaseorderModel, {
			disableSearchFromKey: {
				settlementStatus: false,
				storageStatus: false,
				supplierId: false,
				createTime: false
			},

			defaultSearchFrom: {
				settlementStatus: settlementStatus
			},
			rowSelection: {
				type: 'radio',
				onSelect: (v) => {
					value = v
				},
				onChange: () => {}
			}
		})
		const onOk = () => {
			formData.value.objectId = value.id
			activeSelectObject.value = value
			ObjectIdChangeLoad()
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}
	const openProjectSelect = () => {
		let value = {}
		let content = createVNode(bizSaleProjectModal, {
			rowSelection: {
				type: 'radio',
				onSelect: (v) => {
					value = v
				},
				onChange: () => {}
			}
		})
		const onOk = () => {
			formData.value.objectId = value.id
			activeSelectObject.value = value
			ObjectIdChangeLoad()
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1200px',
			onOk: onOk
		})
	}

	//代收款还款
	const openCollectionReceipt = () => {
		let value = {}
		let content = createVNode(bizCollectionReceiptModel, {
			disableSearchFromKey: {
				playStatus: true,
				createTime: false
			},

			defaultSearchFrom: {
				playStatus: 'Unsettled'
			},
			rowSelection: {
				type: 'radio',
				onSelect: (v) => {
					value = v
				},
				onChange: () => {}
			}
		})
		const onOk = () => {
			formData.value.objectId = value.id
			activeSelectObject.value = value
			formData.value.amount = new Decimal(value.amount).sub(value.settlementAmount).toNumber()
			ObjectIdChangeLoad()
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	//退货单

	const openReturnOrder = () => {
		let value = {}
		let content = createVNode(returnOrderModel, {
			disableSearchFromKey: {
				createTime: false
			},
			defaultSearchFrom: {
				state: 'Unsettled'
			},
			rowSelection: {
				type: 'radio',
				onSelect: (v) => {
					value = v
				},
				onChange: () => {}
			}
		})
		const onOk = () => {
			formData.value.objectId = value.id
			activeSelectObject.value = value
			formData.value.amount = new Decimal(value.amount)
			ObjectIdChangeLoad()
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	//考勤单
	const openLeveApplication = () => {
		let value = {}
		let content = createVNode(leaveApplicationModel, {
			disableSearchFromKey: {
				createTime: false
			},
			defaultSearchFrom: {
				category: []
			},
			rowSelection: {
				type: 'radio',
				onSelect: (v) => {
					value = v
				},
				onChange: () => {}
			}
		})
		const onOk = () => {
			formData.value.objectId = value.id
			activeSelectObject.value = value
			formData.value.amount = new Decimal(value.amount)
			ObjectIdChangeLoad()
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	const baseRule = {
		payer: [required('打款人信息必填')],
		bankName: [required('开户行')],
		bankAccount: [rules.bankAccount, required('银行卡号不能为空')],
		treasurer: [required('请选择财务')],
		amount: [required('收款金额不能为空')],
		remark: [required('备注不能为空')]
	}
	const addSettlementAccountRules = (rule) => {
		return Object.assign(rule, {
			payerTime: [required('付款时间')],
			accountId: [required('收款账户不能为空')]
		})
	}
	const addObjectIdRule = (rule) => {
		return Object.assign(rule, {
			objectId: [required('单号不能为空')]
		})
	}
	const formRules = computed(() => {
		let rule = { ...baseRule }
		if (useSettlementAccount.value) {
			rule = addSettlementAccountRules(rule)
		}
		if (
			[
				'GOODS_EXPENDITURE',
				'CUSTOMER_REBATE',
				'repayment',
				'ReturnAndRefund',
				'TravelExpenses',
				'ProcurementFreight'
			].includes(formData.value.settlementCategory)
		) {
			rule = addObjectIdRule(rule)
		}
		return rule
	})
	watch(
		() => formData.value.settlementCategory,
		() => {
			formData.value.objectId = ''
			activeSelectObject.value = {}
		}
	)
	watch(
		() => useSettlementAccount.value,
		() => {
			formData.value.payerTime = ''
			formData.value.accountId = ''
		}
	)

	const onClose = () => {
		visible.value = false
	}
	const onOpen = () => {
		const { copyUserIdList, approveUserIdList, treasurer } = useProcessParam('Process_make_payment')
		list.value = []
		formData.value = {
			objectId: '',
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList,
			treasurer: treasurer
		}
		visible.value = true
	}
	const onUploadSuccess = (res) => {
		list.value.push(res)
	}
	const { load: onSubmit, loading: sendLoading } = useLoading(async () => {
		try {
			await formRef.value.validate()
		} catch (err) {
			activeKey.value = 'baseInfo'
			return
		}

		if (activeKey.value === 'baseInfo') {
			activeKey.value = 'file-list'

			return
		}

		try {
			await approveFormRef.value.validate()
		} catch (err) {
			activeKey.value = 'approve-info'
			return
		}

		if (activeKey.value !== 'approve-info') {
			activeKey.value = 'approve-info'
			return
		}

		const param = cloneDeep(formData.value)
		const fileIdList = list.value.map((v) => v.id)
		await bizProcessApi.bizProcessStartMakePayment({
			...param,
			useAdvancePayment: useSettlementAccount.value,
			fileIdList
		})
		onClose()
	})

	const quickInput = () => {
		const userInfo = cloneDeep(store.userInfo)
		formData.value.bankName = userInfo.bankName
		formData.value.bankAccount = userInfo.bankAccount
		formData.value.payer = userInfo.name
	}

	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
