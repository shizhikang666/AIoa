<template>
	<xn-form-container
		:title="formData.id ? '编辑供应商' : '增加供应商'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="供应商名称：" name="name">
				<a-input v-model:value="formData.name" placeholder="请输入供应商名称" allow-clear />
			</a-form-item>
			<a-form-item label="供应商别名：" name="aliasName">
				<a-input v-model:value="formData.aliasName" placeholder="别名" allow-clear />
			</a-form-item>
			<a-form-item label="联系人：" name="contacts">
				<a-input v-model:value="formData.contacts" placeholder="请输入联系人" allow-clear />
			</a-form-item>
			<a-form-item label="联系电话：" name="phone">
				<a-input v-model:value="formData.phone" placeholder="请输入联系电话" allow-clear />
			</a-form-item>
			<a-form-item label="开户行：" name="bankName">
				<a-input v-model:value="formData.bankName" placeholder="请输入开户行" allow-clear />
			</a-form-item>
			<a-form-item label="银行账户：" name="bankAccount">
				<a-input v-model:value="formData.bankAccount" placeholder="请输入银行账户" allow-clear />
			</a-form-item>
			<a-form-item label="供应商状态：" name="status">
				<a-select v-model:value="formData.status" placeholder="请选择供应商状态" :options="statusOptions" />
			</a-form-item>
			<a-form-item label="企业性质：" name="enterpriseNature">
				<a-input v-model:value="formData.enterpriseNature" placeholder="请输入企业性质" allow-clear />
			</a-form-item>
			<a-form-item label="税务登记号：" name="taxRegistrationNumber">
				<a-input v-model:value="formData.taxRegistrationNumber" placeholder="请输入税务登记号" allow-clear />
			</a-form-item>
			<a-form-item label="结算方式：" name="paymentMethod">
				<a-input v-model:value="formData.paymentMethod" placeholder="请输入结算方式" allow-clear />
			</a-form-item>
			<a-form-item label="排序编码：" name="sortCode">
				<a-input v-model:value="formData.sortCode" placeholder="请输入排序编码" allow-clear />
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="supplierForm">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import supplierApi from '@/api/biz/supplierApi'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const statusOptions = ref([])

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
		statusOptions.value = tool.dictList('COMMON_STATUS')
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = {
		name: [required('请输入供应商名称')],
		contacts: [required('请输入联系人')],
		phone: [required('请输入联系电话')],
		status: [required('请输入供应商状态')]
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				supplierApi
					.supplierSubmitForm(formDataParam, formDataParam.id)
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
