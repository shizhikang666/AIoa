<template>
	<xn-form-container
		:title="formData.id ? '编辑工资单' : '增加工资单'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="生成用户：" name="user">
				<xn-user-selector
					:org-tree-api="selectorApiFunction.orgTreeApi"
					:user-page-api="selectorApiFunction.userPageApi"
					:user-list-by-id-list-api="selectorApiFunction.checkedUserListApi"
					data-type="object"
					v-model:value="formData.user"
				/>
			</a-form-item>

			<a-form-item label="社保扣款：" name="socialSecurity">
				<a-row>
					<a-col span="6">
						<XnCurrencyInput v-model="formData.socialSecurity"></XnCurrencyInput>
					</a-col>
				</a-row>
			</a-form-item>
			<a-form-item label="工资单月份：" name="salaryTime">
				<a-date-picker
					value-format="YYYY-MM-DD HH:mm:ss"
					:disabledDate="disabledDate"
					v-model:value="formData.salaryTime"
					picker="month"
				/>
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizPayrollForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import bizPayrollApi from '@/api/biz/bizPayrollApi'
	import dayjs from '@/utils/dayjs'
	import { useUserSelector } from '@/composables/useUserSelector'
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	const selectorApiFunction = useUserSelector()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const disabledDate = (date) => {
		const now = dayjs()
		const currentMonthStart = now.startOf('month')
		// 将给定日期转换为 dayjs 对象
		const givenDate = dayjs(date)
		// 判断给定日期是否在本月之前
		return !givenDate.isBefore(currentMonthStart)
	}

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
		user: [required('请输入所属用户')],
		socialSecurity: [required('请输入社保扣款')],
		salaryTime: [required('请选择工资单月份')]
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				bizPayrollApi
					.bizPayrollListGenerate(formDataParam)
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
