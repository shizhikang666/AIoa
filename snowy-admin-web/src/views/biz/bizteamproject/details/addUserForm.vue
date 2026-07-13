<template>
	<xn-form-container
		:title="formData.id ? '邀请成员' : '邀请成员'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-skeleton :loading="loading">
			<a-form v-if="!error" ref="formRef" :model="formData" :rules="formRules" layout="vertical">
				<a-form-item required label="参与人：" name="users">
					<xn-user-selector
						:org-tree-api="selectorApiFunction.orgTreeApi"
						:user-page-api="loadUsers"
						:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
						data-type="objects"
						:userShow="true"
						v-model:value="formData.users"
					/>
				</a-form-item>
			</a-form>
			<error-result v-else @reload="load"></error-result>
		</a-skeleton>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="addUserForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizTeamProjectApi from '@/api/biz/bizTeamProjectApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useLoading } from '@/composables/useLoading'
	import BizTeamProjectUserApi from '@/api/biz/bizTeamProjectUserApi'
	import ErrorResult from '@/components/ErrorResult/ErrorResult.vue'
	import bizTeamProjectUserApi from '@/api/biz/bizTeamProjectUserApi'

	// 传递设计器需要的API
	const selectorApiFunction = useUserSelector()
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)

	const teamUser = ref([])

	const loadUsers = async (param) => {
		const result = await selectorApiFunction.userPageApi(
			Object.assign(param, {
				ignoreList: teamUser.value.map((v) => v.userId).join(',')
			})
		)

		return result
	}

	const { loading, error, load } = useLoading(async () => {
		const id = formData.value.teamProjectId
		teamUser.value = await BizTeamProjectUserApi.bizTeamProjectUserList({
			id: id
		})
	})

	// 打开抽屉
	const onOpen = async (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
		await load()
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

				bizTeamProjectUserApi
					.bizTeamProjectUserAdd(formDataParam)
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
