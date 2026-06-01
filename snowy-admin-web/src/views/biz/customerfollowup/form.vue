<template>
	<xn-form-container
		:title="formData.id ? '编辑客户跟踪记录' : '增加客户跟踪记录'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="跟进时间：" name="followUpTime">
				<a-date-picker v-model:value="formData.followUpTime" value-format="YYYY-MM-DD HH:mm:ss" show-time placeholder="请选择跟进时间" style="width: 100%" />
			</a-form-item>
			<a-form-item label="内容：" name="content">
				<a-input v-model:value="formData.content" placeholder="请输入内容" allow-clear />
			</a-form-item>

		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="customerFollowUpForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import customerFollowUpApi from '@/api/biz/customerFollowUpApi'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)

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
		customerId: [required('请输入客户编号')],
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				customerFollowUpApi
					.customerFollowUpSubmitForm(formDataParam, formDataParam.id)
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
