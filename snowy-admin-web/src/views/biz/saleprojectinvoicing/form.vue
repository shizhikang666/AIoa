<template>
	<xn-form-container
		:title="formData.id ? '编辑开票信息表' : '增加开票信息表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="项目编号：" name="projectId">
				<a-input v-model:value="formData.projectId" placeholder="请输入项目编号" allow-clear />
			</a-form-item>
			<a-form-item label="开票金额：" name="amount">
				<a-input v-model:value="formData.amount" placeholder="请输入开票金额" allow-clear />
			</a-form-item>
			<a-form-item label="开票状态：" name="invoicingState">
				<a-select v-model:value="formData.invoicingState" placeholder="请选择开票状态" :options="invoicingStateOptions" />
			</a-form-item>
			<a-form-item label="开票类型：" name="invoicingCategory">
				<a-select v-model:value="formData.invoicingCategory" placeholder="请选择开票类型" :options="invoicingCategoryOptions" />
			</a-form-item>
			<a-form-item label="流程编号：" name="processId">
				<a-input v-model:value="formData.processId" placeholder="请输入流程编号" allow-clear />
			</a-form-item>
			<a-form-item label="备注：" name="remark">
				<a-input v-model:value="formData.remark" placeholder="请输入备注" allow-clear />
			</a-form-item>
			<a-form-item label="开票公司：" name="companyName">
				<a-input v-model:value="formData.companyName" placeholder="请输入开票公司" allow-clear />
			</a-form-item>
			<a-form-item label="客户公司：" name="customerCompany">
				<a-input v-model:value="formData.customerCompany" placeholder="请输入客户公司" allow-clear />
			</a-form-item>
			<a-form-item label="单位全称：" name="unit">
				<a-input v-model:value="formData.unit" placeholder="请输入单位全称" allow-clear />
			</a-form-item>
			<a-form-item label="联系电话：" name="phone">
				<a-input v-model:value="formData.phone" placeholder="请输入联系电话" allow-clear />
			</a-form-item>
			<a-form-item label="纳税人号：" name="taxpayer">
				<a-input v-model:value="formData.taxpayer" placeholder="请输入纳税人号" allow-clear />
			</a-form-item>
			<a-form-item label="对公账户：" name="corporateAccount">
				<a-input v-model:value="formData.corporateAccount" placeholder="请输入对公账户" allow-clear />
			</a-form-item>
			<a-form-item label="开户银行：" name="bankName">
				<a-input v-model:value="formData.bankName" placeholder="请输入开户银行" allow-clear />
			</a-form-item>
			<a-form-item label="单位地址：" name="unitAddress">
				<a-input v-model:value="formData.unitAddress" placeholder="请输入单位地址" allow-clear />
			</a-form-item>
			<a-form-item label="发票地址：" name="harvestAddress">
				<a-input v-model:value="formData.harvestAddress" placeholder="请输入发票地址" allow-clear />
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizSaleProjectInvoicingForm">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizSaleProjectInvoicingApi from '@/api/biz/bizSaleProjectInvoicingApi'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const invoicingStateOptions = ref([])
	const invoicingCategoryOptions = ref([])

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
		invoicingStateOptions.value = tool.dictList('INVOICING_STATE')
		invoicingCategoryOptions.value = tool.dictList('InvoicingCategory')
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = {
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizSaleProjectInvoicingApi
					.bizSaleProjectInvoicingSubmitForm(formDataParam, formDataParam.id)
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
