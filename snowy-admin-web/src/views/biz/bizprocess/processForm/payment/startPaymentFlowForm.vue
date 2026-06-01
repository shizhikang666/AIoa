<template>
	<xn-form-container title="添加收款记录" :width="550" :visible="visible" :destroy-on-close="true" @close="onClose">
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<!--			<a-form-item label="打款人：" name="payer">-->
			<!--				<a-input placeholder="请输入打款人" v-model:value="formData.payer"></a-input>-->
			<!--			</a-form-item>-->
			<a-form-item label="收款账户：" name="accountId">
				<a-select placeholder="请选择收款账户" v-model:value="formData.accountId" :options="accountList"></a-select>
			</a-form-item>
			<a-form-item label="收款类型：" name="settlementCategory">
				<!--				<a-select-->
				<!--					placeholder="选择结算类型"-->
				<!--					v-model:value="formData.settlementCategory"-->
				<!--					:options="settlementCategoryList"-->
				<!--				></a-select>-->

				<a-cascader
					v-model:value="formData.settlementCategory"
					:fieldNames="{
						children: 'children',
						label: 'dictLabel',
						value: 'dictValue'
					}"
					:options="settlementCategoryList"
					placeholder="选择结算类型"
					change-on-select
				/>
			</a-form-item>
			<a-form-item v-if="formData.settlementCategory === 'LoanRepayment'" label="借支单：" name="objectId">
				<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openBizDebitNoteModel">
					{{
						activeSelectObject.id ? activeSelectObject.id + '(' + activeSelectObject.remark + ')' : '（借款/代付）单'
					}}
				</a-typography-link>
			</a-form-item>
			<a-form-item label="收款金额：" name="amount">
				<XnCurrencyInput :min="0" v-model:value="formData.amount" placeholder="请输入收款金额" />
			</a-form-item>

			<a-form-item label="备注：" name="remark">
				<a-textarea
					v-model:value="formData.remark"
					:placeholder="remarkPlaceholder"
					:auto-size="{ minRows: 5, maxRows: 5 }"
				/>
			</a-form-item>
			<a-form-item label="收款日期：" name="payerTime">
				<a-date-picker v-model:value="formData.payerTime" value-format="YYYY-MM-DD HH:mm:ss" show-time></a-date-picker>
			</a-form-item>

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
		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>

			<a-button type="primary" @click="onSubmit" :loading="sendLoading">发送</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="startPaymentFlowForm">
	import { required } from '@/utils/formRules'
	import { message } from 'ant-design-vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import userApi from '@/api/sys/userApi'
	import userCenterApi from '@/api/sys/userCenterApi'
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import SettlementAccountApi from '@/api/biz/settlementAccountApi'
	import bizProcessApi from '@/api/biz/bizProcessApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useProcessParam } from '@/composables/useProcessParam'
	import dayjs from 'dayjs'
	import { computed, createVNode } from 'vue'
	import { Decimal } from 'decimal.js'
	import bizDebitNoteModel from '@/views/biz/bizdebitnote/model/index.vue'
	import { App } from 'ant-design-vue'

	const { modal } = App.useApp()
	const settlementCategoryList = computed(() => {
		return tool.dictTypeList(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'INCOME_CATEGORY']).filter((item) => {
			return item.dictValue !== 'PROJECT_PLAY'
		})
	})
	const sendLoading = ref(false)
	// 定义emit事件
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const accountList = ref([])

	const remarkPlaceholder = computed(() => {
		if (formData.value.settlementCategory === 'LoanRepayment') {
			return '提示：请输入xx借贷还款'
		}
		if (formData.value.settlementCategory === 'Collection') {
			return '提示：请输入代收款理由'
		}
		return '请输入收款备注'
	})

	SettlementAccountApi.settlementAccountList().then((res) => {
		accountList.value = res.map((v) => {
			return {
				label: v.accountName,
				value: v.id
			}
		})
	})
	// 格式化当前时间
	const formattedDate = dayjs().format('YYYY-MM-DD HH:mm:ss')
	// 打开抽屉
	const onOpen = () => {
		visible.value = true
		const { copyUserIdList, approveUserIdList, treasurer } = useProcessParam('Process_payment')
		activeSelectObject.value = {}

		formData.value = {
			accountId: '',
			payerTime: formattedDate,
			approveUserIdList: approveUserIdList,
			copyUserIdList: copyUserIdList,
			treasurer: treasurer,
			amount: 0
		}
	}
	// 关闭抽屉
	const onClose = () => {
		emit('successful')
		visible.value = false
	}
	// 默认要校验的

	const formRules = computed(() => {
		let rule = {
			remark: [required('备注必填')],
			settlementCategory: [required('结算类型不能为空')],
			treasurer: [required('请选择财务')],
			payerTime: [required('付款时间')],
			amount: [required('收款金额不能为空')],
			accountId: [required('收款账户为空')]
		}
		//'GOODS_EXPENDITURE', 'CUSTOMER_REBATE'
		if (['LoanRepayment'].includes(formData.value.settlementCategory)) {
			rule = Object.assign(rule, {
				objectId: [required('单号不能为空')]
			})
		}

		return rule
	})

	// 站内信分类字典

	// 验证并提交数据
	const onSubmit = async () => {
		try {
			await formRef.value.validate()
		} catch (e) {
			return
		}
		sendLoading.value = true

		try {
			let form = cloneDeep(formData.value)
			form.settlementCategory = form.settlementCategory.join('/')
			await bizProcessApi.bizProcessStart(form)
			onClose()
		} finally {
			sendLoading.value = false
		}
	}
	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()

	const activeSelectObject = ref({})
	//代收款还款bizDebitNoteModel
	const openBizDebitNoteModel = () => {
		let value = {}
		let content = createVNode(bizDebitNoteModel, {
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
			if (formData.value.settlementCategory === 'LoanRepayment') {
				formData.value.amount = new Decimal(value.amount).sub(value.settlementAmount).toNumber()
			}
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		onOpen
	})
</script>
