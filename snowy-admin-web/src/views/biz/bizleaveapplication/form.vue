<template>
	<xn-form-container
		:title="formData.id ? '编辑请假记录表' : '增加请假记录表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="请假userId：" name="userId">
				<a-input v-model:value="formData.userId" placeholder="请输入请假userId" allow-clear />
			</a-form-item>
			<a-form-item label="流程iD：" name="processId">
				<a-input v-model:value="formData.processId" placeholder="请输入流程iD" allow-clear />
			</a-form-item>
			<a-form-item label="请假类型（如年假、病假、事假等）：" name="category">
				<a-select
					v-model:value="formData.category"
					placeholder="请选择请假类型（如年假、病假、事假等）"
					:options="categoryOptions"
				/>
			</a-form-item>
			<a-form-item label="天数：" name="amount">
				<a-input v-model:value="formData.amount" placeholder="请输入天数" allow-clear />
			</a-form-item>
			<a-form-item label="请假原因：" name="remark">
				<a-input v-model:value="formData.remark" placeholder="请输入请假原因" allow-clear />
			</a-form-item>
			<a-form-item label="请假开始日期：" name="startTime">
				<a-date-picker
					v-model:value="formData.startTime"
					value-format="YYYY-MM-DD HH:mm:ss"
					show-time
					placeholder="请选择请假开始日期"
					style="width: 100%"
				/>
			</a-form-item>
			<a-form-item label="请假结束日期：" name="endTime">
				<a-date-picker
					v-model:value="formData.endTime"
					value-format="YYYY-MM-DD HH:mm:ss"
					show-time
					placeholder="请选择请假结束日期"
					style="width: 100%"
				/>
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizLeaveApplicationForm">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizLeaveApplicationApi from '@/api/biz/bizLeaveApplicationApi'
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
		categoryOptions.value = tool.dictList('APPROVAL_PROCESS')
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
				bizLeaveApplicationApi
					.bizLeaveApplicationSubmitForm(formDataParam, formDataParam.id)
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
