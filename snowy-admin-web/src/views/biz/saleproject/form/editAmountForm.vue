<template>
	<xn-form-container title="修改项目金额" v-model:open="open" :destroy-on-close="true" @close="onClose">
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="合同金额" name="initPrice">
				<XnCurrencyInput v-model:value="formData.initPrice"></XnCurrencyInput>
			</a-form-item>

			<a-form-item label="备注" name="remark">
				<a-textarea v-model:value="formData.remark" :rows="4" />
			</a-form-item>
		</a-form>

		<template #footer>
			<a-row justify="end">
				<a-col>
					<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
					<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
				</a-col>
			</a-row>
		</template>
	</xn-form-container>
</template>
<script setup lang="js">
	import { ref, useTemplateRef } from 'vue'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'
	import { required } from '@/utils/formRules'

	import { cloneDeep } from 'lodash-es'

	const formRef = useTemplateRef('formRef')
	const formData = ref({})
	const open = ref(false)
	const emit = defineEmits({ successful: null })

	const submitLoading = ref(false)
	const formRules = {
		initPrice: [required('请输入合同金额')]
	}

	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}

		open.value = false
	}
	const onOpen = (data) => {
		open.value = true
		formData.value = Object.assign(formData.value, data)
		formData.value.remark = ''
	}

	const onSubmit = async () => {
		await formRef.value.validate()
		submitLoading.value = true
		const formDataParam = cloneDeep(formData.value)

		try {
			await bizSaleProjectApi.editBizSaleProjectAmount(formDataParam)

			emit('successful')

			onClose()
		} finally {
			submitLoading.value = false
		}
	}

	defineExpose({
		onOpen
	})
</script>
<style scoped></style>
