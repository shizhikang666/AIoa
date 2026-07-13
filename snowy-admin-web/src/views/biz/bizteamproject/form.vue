<template>
	<xn-form-container
		:title="formData.id ? '编辑待办事项管理' : '增加待办事项管理'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="项目名称：" name="name">
				<a-input v-model:value="formData.name" placeholder="请输入项目名称" allow-clear />
			</a-form-item>

			<a-form-item label="项目描述：" name="description">
				<a-textarea v-model:value="formData.description" placeholder="请输入项目描述" allow-clear />
			</a-form-item>

			<!--			<a-form-item label="参与人：" name="users">-->
			<!--				<xn-user-selector-->
			<!--					:org-tree-api="selectorApiFunction.orgTreeApi"-->
			<!--					:user-page-api="selectorApiFunction.userPageApi"-->
			<!--					:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"-->
			<!--					data-type="object"-->
			<!--					v-model:value="formData.users"-->
			<!--				/>-->
			<!--			</a-form-item>-->
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizTeamProjectForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizTeamProjectApi from '@/api/biz/bizTeamProjectApi'
	import { useUserSelector } from '@/composables/useUserSelector'

	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()
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
		name: [required('请输入项目名称')]
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizTeamProjectApi
					.bizTeamProjectSubmitForm(formDataParam, formDataParam.id)
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
