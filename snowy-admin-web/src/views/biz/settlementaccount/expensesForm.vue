<template>
	<xn-form-container
		:title="formData.id ? '编辑支出记录表' : '增加支出记录表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form :labelCol="{ span: 3 }" labelAlign="left" ref="formRef" :model="formData" :rules="formRules">
			<a-form-item label="支出账号：" name="targetId">
				<a-select placeholder="请选择收款账户" v-model:value="formData.targetId" :options="accountList"></a-select>
			</a-form-item>

			<a-form-item label="支出时间：" name="payerTime">
				<a-date-picker
					v-model:value="formData.payerTime"
					value-format="YYYY-MM-DD HH:mm:ss"
					show-time
					placeholder="请选择付款时间"
					style="width: 100%"
				/>
			</a-form-item>
			<a-form-item label="结算分类：" name="settlementCategory">
				<a-select
					placeholder="请输入结算分类"
					v-model:value="formData.settlementCategory"
					:options="settlementCategoryList"
				></a-select>
			</a-form-item>
			<a-form-item v-if="formData.settlementCategory === 'repayment'" label="代收款单：" name="objectId">
				<a-typography-link :type="formData.objectId ? '' : 'danger'" @click="openCollectionReceipt">
					{{ activeSelectObject.id ? activeSelectObject.id + '(' + activeSelectObject.remark + ')' : '代收款单' }}
				</a-typography-link>
			</a-form-item>

			<a-form-item label="金额：" name="amount">
				<XnCurrencyInput :min="0" v-model:value="formData.amount" placeholder="请输入支出金额" />
			</a-form-item>
			<a-form-item label="收款人：" name="payer">
				<a-input v-model:value="formData.payer" placeholder="请输入收款人" allow-clear />
			</a-form-item>
			<a-form-item label="开户行：" name="bankName">
				<a-input v-model:value="formData.bankName" placeholder="请输入开户行" allow-clear />
			</a-form-item>
			<a-form-item label="银行账户：" name="bankAccount">
				<a-input v-model:value="formData.bankAccount" placeholder="请输入银行账户" allow-clear />
			</a-form-item>
			<a-form-item label="备注：" name="remark">
				<a-textarea v-model:value="formData.remark" placeholder="请输入备注" :auto-size="{ minRows: 5, maxRows: 5 }" />
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizExpenditureRecordForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import settlementAccountApi from '@/api/biz/settlementAccountApi'
	import { useSettlementAccount } from '@/composables/useSettlementAccount'
	import { computed, createVNode } from 'vue'
	import tool from '@/utils/tool'
	import dayjs from '@/utils/dayjs'
	import bizCollectionReceiptModel from '@/views/biz/bizcollectionreceipt/model/index.vue'
	import { Decimal } from 'decimal.js'
	import { App } from 'ant-design-vue'

	const { modal } = App.useApp()
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const { accountList, loadSettlementAccountApi } = useSettlementAccount()
	const settlementCategoryList = computed(() => {
		return tool.dictListByPath(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY']).filter((item) => {
			return item.value !== 'PROJECT_PLAY'
		})
	})
	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		loadSettlementAccountApi.load().then()
		activeSelectObject.value = {}
		formData.value = {}
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
			formData.value.payerTime = dayjs().format('YYYY-MM-DD HH:mm:ss')
		}
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}

	const formRules = computed(() => {
		let rule = {
			targetId: required('银行账号必填'),
			payerTime: required('支出时间必填'),
			settlementCategory: required('结算分类必填'),
			payer: required('收款人必填'),
			amount: required('金额必填')
		}
		//'GOODS_EXPENDITURE', 'CUSTOMER_REBATE'
		if (['repayment'].includes(formData.value.settlementCategory)) {
			rule = Object.assign(rule, {
				objectId: [required('单号不能为空')]
			})
		}

		return rule
	})

	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				settlementAccountApi
					.settlementAccountExpenses(formDataParam, formDataParam.id)
					.then(() => {
						onClose()
						emit('successful')
					})
					.finally(() => {
						submitLoading.value = false
					})
			})
			.catch(() => {})
	}

	const activeSelectObject = ref({})
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
		}
		modal.confirm({
			icon: null,
			content: content,
			width: '1000px',
			onOk: onOk
		})
	}

	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
