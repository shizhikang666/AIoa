<template>
	<xn-form-container
		:title="formData.id ? '编辑客户' : '增加客户'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-form-item label="客户单位名称：" name="name">
				<a-input v-model:value="formData.name" placeholder="请输入客户名称" allow-clear />
			</a-form-item>
			<a-form-item label="联系人：" name="contacts">
				<a-input v-model:value="formData.contacts" placeholder="请输入联系人" allow-clear />
			</a-form-item>
			<a-form-item label="联系电话：" name="phone">
				<a-input v-model:value="formData.phone" placeholder="请输入联系电话" allow-clear />
			</a-form-item>
			<a-form-item label="客户来源：" name="sourceType">
				<a-select
					:disabled="disabledForm.sourceType"
					v-model:value="formData.sourceType"
					placeholder="请选择客户来源"
					:options="sourceTypeOptions"
				/>
			</a-form-item>
			<a-form-item label="客户类型：" name="customType">
				<a-select v-model:value="formData.customType" placeholder="请选择客户类型" :options="customTypeOptions" />
			</a-form-item>
			<a-form-item label="客户地区：" name="address">
				<a-cascader
					change-on-select
					:fieldNames="{ label: 'name', value: 'name', children: 'children' }"
					v-model:value="formData.address"
					:options="pacOptions"
					placeholder="选择地区"
				/>
				<!--				<a-input v-model:value="formData.address" placeholder="请输入客户地区" allow-clear />-->
				<!--			-->
			</a-form-item>
			<a-form-item label="详细地址：" name="detailsAddress">
				<a-input v-model:value="formData.detailsAddress" placeholder="请输入详细地址" allow-clear />
			</a-form-item>
			<a-form-item v-if="showFirstContactTime" label="首次联系时间：" name="firstContactTime">
				<a-date-picker
					v-model:value="formData.firstContactTime"
					value-format="YYYY-MM-DD HH:mm:ss"
					show-time
					placeholder="请选择首次联系时间"
					style="width: 100%"
				/>
			</a-form-item>
			<a-form-item label="营业执照：" name="fileId">
				<a-col>
					<a-space direction="vertical">
						<a-row>
							<a-button type="primary" @click="() => uploadFormRef.openUpload()">
								<UploadOutlined />
								文件上传
							</a-button>
						</a-row>
						<a-row>
							<a-image v-if="formData.downloadPath" :width="200" :src="formData.downloadPath" />
						</a-row>
					</a-space>
				</a-col>
			</a-form-item>

			<a-form-item label="备注：" name="remark">
				<a-textarea v-model:value="formData.remark" placeholder="请输入备注" allow-clear />
			</a-form-item>

			<!--			<a-form-item label="状态：" name="status">-->
			<!--				<a-select v-model:value="formData.status" placeholder="请选择状态" :options="statusOptions" />-->
			<!--			</a-form-item>-->
			<!--			<a-form-item label="排序编码：" name="sortCode">-->
			<!--				<a-input v-model:value="formData.sortCode" placeholder="请输入排序编码" allow-clear />-->
			<!--			</a-form-item>-->
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
	<uploadForm :accept="'image/*'" :multiple="false" ref="uploadFormRef" @successful="onUploadSuccess" />
</template>

<script setup name="customerForm">
	import tool from '@/utils/tool'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import customerApi from '@/api/biz/customerApi'
	import UploadForm from '@/views/biz/file/uploadForm.vue'
	import { useTemplateRef } from 'vue'

	const uploadFormRef = useTemplateRef('uploadFormRef')
	const { defaultForm, disabledForm } = defineProps({
		showFirstContactTime: {
			type: Boolean,
			default: false
		},
		defaultForm: {
			type: Object, // 指定类型为对象
			default: () => ({}) // 设置默认值为空对象
		},
		disabledForm: {
			type: Object, // 指定类型为对象
			default: () => ({}) // 设置默认值为空对象
		}
	})
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const submitLoading = ref(false)
	const sourceTypeOptions = ref([])
	const customTypeOptions = ref([])
	const statusOptions = ref([])
	const pacOptions = ref([])

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			recordData.address = record.address.split('/')
			formData.value = Object.assign({}, recordData)
		} else {
			formData.value = Object.assign({}, defaultForm)
		}

		sourceTypeOptions.value = tool.dictListByPath(['CUSTOMER', 'CUSTOMER_SOURCE'])
		customTypeOptions.value = tool.dictListByPath(['CUSTOMER', 'CUSTOMER_TYPE'])
		pacOptions.value = tool.pcaDataAll()
		statusOptions.value = tool.dictList('COMMON_STATUS')
		formData.value.status = statusOptions.value[0].value
	}
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}

	// 默认要校验的
	const formRules = {
		name: [required('请输入客户单位名称')],
		contacts: [required('请输入联系人')],
		phone: [required('请输入联系电话')],
		address: [required('请选择客户地区')],
		sourceType: [required('请选择客户来源')],
		customType: [required('请选择客户类型')],
		status: [required('请选择状态')],
		fileId: [required('请上传营业执照')]
	}

	const onUploadSuccess = (v) => {
		formData.value.fileId = v.id
		formData.value.downloadPath = v.downloadPath
		uploadFormRef.value.onClose()
	}
	// 验证并提交数据
	const onSubmit = async () => {
		submitLoading.value = true
		try {
			try {
				await formRef.value.validate()
			} catch (e) {
				return
			}
			const formDataParam = cloneDeep(formData.value)
			formDataParam.address = formDataParam.address.join('/')
			await customerApi.customerSubmitForm(formDataParam, formDataParam.id)
			onClose()
			emit('successful')
		} finally {
			submitLoading.value = false
		}
	}
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
