<template>
	<xn-form-container
		:title="formData.id ? '编辑支出记录表' : '增加支出记录表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="支出账户：" name="targetId">
				<a-select
					show-search
					:filter-option="filterOption"
					placeholder="请选择账户"
					v-model:value="formData.targetId"
					:options="accountList"
				></a-select>
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="editExpenditureTargetForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizExpenditureRecordApi from '@/api/biz/bizExpenditureRecordApi'
	import { useSettlementAccount } from '@/composables/useSettlementAccount'
	import { useSelectFilterOption } from '@/composables/useSelectFilterOption'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const { accountList, loadSettlementAccountApi } = useSettlementAccount()
	const filterOption = useSelectFilterOption()

	// 打开抽屉
	const onOpen = async (record) => {
		open.value = true

		await loadSettlementAccountApi.load()

		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)

			formData.value.currentTargetId = recordData.targetId
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
		targetId: [required('请选择切换的账户！')]
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizExpenditureRecordApi
					.bizExpenditureRecordEditAccount(formDataParam)
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
