<template>
	<xn-form-container
		:title="formData.id ? '编辑支出记录表' : '增加支出记录表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<!--			<a-form-item label="对象编号：" name="objectId">-->
			<!--				<a-input v-model:value="formData.objectId" placeholder="请输入对象编号" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="支出账号编号：" name="targetId">-->
			<!--				<a-input v-model:value="formData.targetId" placeholder="请输入支出账号编号" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="流水编号：" name="serialId">-->
			<!--				<a-input v-model:value="formData.serialId" placeholder="请输入流水编号" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="流程实例编号：" name="processId">-->
			<!--				<a-input v-model:value="formData.processId" placeholder="请输入流程实例编号" allow-clear />-->
			<!--			</a-form-item>-->
			<a-form-item label="结算分类：" name="settlementCategory">
				<a-select
					placeholder="选择结算类型"
					v-model:value="formData.settlementCategory"
					:options="settlementCategoryList"
				></a-select>
			</a-form-item>
			<!--			<a-form-item label="收款人：" name="payer">-->
			<!--				<a-input v-model:value="formData.payer" placeholder="请输入收款人" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="开户行：" name="bankName">-->
			<!--				<a-input v-model:value="formData.bankName" placeholder="请输入开户行" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="银行账户：" name="bankAccount">-->
			<!--				<a-input v-model:value="formData.bankAccount" placeholder="请输入银行账户" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="备注：" name="remark">-->
			<!--				<a-input v-model:value="formData.remark" placeholder="请输入备注" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="付款时间：" name="payerTime">-->
			<!--				<a-date-picker v-model:value="formData.payerTime" value-format="YYYY-MM-DD HH:mm:ss" show-time placeholder="请选择付款时间" style="width: 100%" />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="支出金额：" name="amount">-->
			<!--				<a-input v-model:value="formData.amount" placeholder="请输入支出金额" allow-clear />-->
			<!--			</a-form-item>-->
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
	import bizExpenditureRecordApi from '@/api/biz/bizExpenditureRecordApi'
	import tool from '@/utils/tool'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const settlementCategoryList = tool
		.dictListByPath(['SETTLEMENT_ACCOUNT', 'SETTLEMENT_CATEGORY', 'PAY_CATEGORY'])
		.filter((str) => {
			return !['ReturnAndRefund', 'GOODS_EXPENDITURE', 'CUSTOMER_REBATE', 'repayment', 'proxyPayment'].some(
				(item) => item === str.value
			)
		})

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = {}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizExpenditureRecordApi
					.bizExpenditureRecordSubmitForm(formDataParam, formDataParam.id)
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
