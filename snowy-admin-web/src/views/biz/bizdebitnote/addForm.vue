<template>
	<xn-form-container title="历史借款录入" :width="700" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="结算账户：" name="accountId">
				<a-row :gutter="16">
					<a-col span="12">
						<a-select
							:filter-option="filterOption"
							placeholder="请选择结算账户"
							v-model:value="formData.accountId"
							:options="accountList"
						></a-select>
					</a-col>
					<a-col>
						<a-popover trigger="click">
							<template #content> 结算账户用于绑定账户所属公司，不计入支出</template>

							<a-button type="link" shape="circle">
								<template #icon>
									<QuestionCircleOutlined />
								</template>
							</a-button>
						</a-popover>
					</a-col>
				</a-row>
			</a-form-item>
			<a-form-item label="借款金额：" name="amount">
				<a-row :gutter="16">
					<a-col span="12">
						<XnCurrencyInput v-model:value="formData.amount" placeholder="请输入借款金额" allow-clear />
					</a-col>
				</a-row>
			</a-form-item>
			<a-form-item label="已还款金额：" name="historyAmount">
				<a-row :gutter="16">
					<a-col span="12">
						<XnCurrencyInput v-model:value="formData.historyAmount" placeholder="请输入已还款金额" allow-clear />
					</a-col>
				</a-row>
			</a-form-item>
			<a-form-item label="日期：" name="createTime">
				<a-row :gutter="16">
					<a-col span="12">
						<a-date-picker v-model:value="formData.createTime" value-format="YYYY-MM-DD HH:mm:ss"></a-date-picker>
					</a-col>
				</a-row>
			</a-form-item>
			<a-form-item label="备注：" name="remark">
				<a-row :gutter="16">
					<a-col span="20">
						<a-textarea v-model:value="formData.remark" placeholder="请输入备注" allow-clear />
					</a-col>
				</a-row>
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="addBizDebitNoteForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizDebitNoteApi from '@/api/biz/bizDebitNoteApi'
	import { useSettlementAccount } from '@/composables/useSettlementAccount'
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
	loadSettlementAccountApi.load().then(() => {})
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
	const formRules = {
		accountId: [required('请选择账户')],
		remark: [required('请输入备注')],
		amount: [required('请输入借款金额')],
		historyAmount: [required('请输入已还款金额')],
		createTime: [required('借款日期')]
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizDebitNoteApi
					.bizDebitNoteSubmitHistory(formDataParam)
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
