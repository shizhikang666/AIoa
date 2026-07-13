<template>
	<xn-form-container
		:is-use-modal="true"
		:title="formData.id ? '' : ''"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<br />
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="horizontal">
			<!--			<a-form-item label="团队编号：" name="teamProjectId">-->
			<!--				<a-input v-model:value="formData.teamProjectId" placeholder="请输入团队编号" allow-clear />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="事项状态：" name="status">-->
			<!--				<a-input v-model:value="formData.status" placeholder="请输入事项状态" allow-clear />-->
			<!--			</a-form-item>-->
			<a-form-item label="任务目标：" name="contentText">
				<a-textarea v-model:value="formData.contentText" placeholder="请输入任务目标" allow-clear />
			</a-form-item>
			<a-form-item label="参与人：" name="users">
				<a-row>
					<a-space>
						<a-avatar :src="userInfo.avatar"></a-avatar>
						<xn-user-selector
							:org-tree-api="selectorApiFunction.orgTreeApi"
							:user-page-api="loadUsers"
							:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
							data-type="objects"
							:userShow="true"
							v-model:value="formData.users"
						/>
					</a-space>
				</a-row>
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizTeamProjectTaskForm">
	import { cloneDeep } from 'lodash-es'
	import bizTeamProjectTaskApi from '@/api/biz/bizTeamProjectTaskApi'
	import { useUserSelector } from '@/composables/useUserSelector'
	import { useLoading } from '@/composables/useLoading'
	import BizTeamProjectUserApi from '@/api/biz/bizTeamProjectUserApi'
	import { globalStore } from '@/store'

	const store = globalStore()
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
	const userInfo = computed(() => {
		return store.userInfo
	})
	const loadUsers = async (param) => {
		return await selectorApiFunction.userPageApi(
			Object.assign(param, {
				userIdList: teamUser.value.map((v) => v.userId).join(','),
				ignoreList: userInfo.value.id
			})
		)
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
	const formRules = {}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizTeamProjectTaskApi
					.bizTeamProjectTaskSubmitForm(formDataParam, formDataParam.id)
					.then((v) => {
						onClose()
						emit('successful', v)
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
