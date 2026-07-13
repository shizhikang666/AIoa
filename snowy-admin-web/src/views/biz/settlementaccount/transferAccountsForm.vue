<template>
	<xn-form-container
		:title="formData.id ? '转账记录' : '转账记录'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="支出账号：" name="expensesAccountId">
				<a-select
					show-search
					:filter-option="filterOption"
					placeholder="请选择收款账户"
					v-model:value="formData.expensesAccountId"
					:options="accountList"
				></a-select>
			</a-form-item>
			<a-form-item label="收入账号：" name="revenueAccountId">
				<a-select
					show-search
					:filter-option="filterOption"
					placeholder="请选择收款账户"
					v-model:value="formData.revenueAccountId"
					:options="accountList"
				></a-select>
			</a-form-item>
			<a-form-item label="操作时间：" name="payerTime">
				<a-date-picker
					v-model:value="formData.payerTime"
					value-format="YYYY-MM-DD HH:mm:ss"
					show-time
					placeholder="请选择付款时间"
					style="width: 100%"
				/>
			</a-form-item>

			<a-form-item label="金额：" name="amount">
				<XnCurrencyInput :min="0" v-model:value="formData.amount" placeholder="请输入支出金额" />
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
	import { computed } from 'vue'
	import tool from '@/utils/tool'
	import dayjs from '@/utils/dayjs'
	import { useSelectFilterOption } from '@/composables/useSelectFilterOption'

	const filterOption = useSelectFilterOption()
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const { accountList, loadSettlementAccountApi } = useSettlementAccount()
	const settlementCategoryList = computed(() => {
		return tool.dictListByPath(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'INCOME_CATEGORY']).filter((item) => {
			return item.value !== 'PROJECT_PLAY'
		})
	})
	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		loadSettlementAccountApi.load().then()
		formData.value.payerTime = dayjs().format('YYYY-MM-DD HH:mm:ss')
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = {
		expensesAccountId: required('银行账号必填'),
		revenueAccountId: required('银行账号必填'),
		payerTime: required('支出时间必填'),
		settlementCategory: required('结算分类必填'),
		payer: required('收款人必填'),
		amount: required('金额必填')
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				settlementAccountApi
					.settlementAccountTransfer(formDataParam)
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
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
