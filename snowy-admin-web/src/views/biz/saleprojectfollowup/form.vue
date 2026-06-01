<template>
	<xn-form-container
		:title="formData.id ? '编辑销售项目跟踪记录' : '增加销售项目跟踪记录'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="跟进项目编号：" name="projectId">
				<a-input v-model:value="formData.projectId" placeholder="请输入跟进项目编号" allow-clear />
			</a-form-item>
			<a-form-item label="跟进时间：" name="followUpTime">
				<a-date-picker v-model:value="formData.followUpTime" value-format="YYYY-MM-DD HH:mm:ss" show-time placeholder="请选择跟进时间" style="width: 100%" />
			</a-form-item>
			<a-form-item label="跟进类型：" name="category">
				<a-select v-model:value="formData.category" placeholder="请选择跟进类型" :options="categoryOptions" />
			</a-form-item>
			<a-form-item label="内容：" name="content">
				<a-textarea v-model:value="formData.content" placeholder="请输入内容" :auto-size="{ minRows: 3, maxRows: 5 }" />
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="saleProjectFollowUpForm">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import saleProjectFollowUpApi from '@/api/biz/saleProjectFollowUpApi'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const categoryOptions = ref([])

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
		categoryOptions.value = tool.dictListByPath('SALE_PROJECT','FOLLOW_UP_CATEGORY')
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = {
		projectId: [required('请输入跟进项目编号')],
		followUpTime: [required('请输入跟进时间')],
		category: [required('请输入跟进类型')],
		content: [required('请输入内容')],
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				saleProjectFollowUpApi
					.saleProjectFollowUpSubmitForm(formDataParam, formDataParam.id)
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
