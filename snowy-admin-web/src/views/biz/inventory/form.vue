<template>
	<xn-form-container
		:title="formData.id ? '编辑仓库库存' : '增加仓库库存'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="变化库存：" name="amount">
				<a-input type="number" v-model:value="formData.amount" placeholder="请输入变化库存" allow-clear />
			</a-form-item>
			<a-form-item label="盘点时间：" name="deliveryTime">
				<a-date-picker
					v-model:value="formData.deliveryTime"
					value-format="YYYY-MM-DD HH:mm:ss"
					show-time
					placeholder="出入库时间"
					style="width: 100%"
				/>
			</a-form-item>
			<a-form-item label="备注：" name="remark">
				<a-textarea v-model:value="formData.remark"></a-textarea>
			</a-form-item>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="inventoryForm">
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import inventoryApi from '@/api/biz/inventoryApi'
	import deliveryRecordApi from '@/api/biz/deliveryRecordApi'
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
			console.log(record)
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, { warehousesId: recordData.warehousesId, productId: recordData.productId })
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
		amount: [required('请输入字典名称')],
		deliveryTime: [required('请选择字典值')],
		remark: [required('请输入备注！')]
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				deliveryRecordApi
					.deliveryRecordAdd(formDataParam)
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
