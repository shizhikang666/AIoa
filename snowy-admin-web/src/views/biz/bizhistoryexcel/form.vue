<template>
	<xn-form-container
		:title="formData.id ? '编辑历史EXCLE数据表' : '增加历史EXCLE数据表'"
		:width="700"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
			<a-upload
				v-if="!formData.file"
				name="file"
				:accept="'.xlsx,.xls'"
				:showUploadList="false"
				:customRequest="handleCustomRequest"
			>
				<a-button>
					<upload-outlined />
					选择Excel文件
				</a-button>
			</a-upload>

			<XnFilePreview
				ref="filePreviewRef"
				@goBack="formData.file = null"
				v-if="formData.file"
				:src="formData.file"
				fileType="xls"
			></XnFilePreview>
		</a-form>
		<template #footer>
			<a-button style="margin-right: 8px" @click="onClose">关闭</a-button>
			<a-button type="primary" @click="onSubmit" :loading="submitLoading">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="bizHistoryExcelForm">
	import { cloneDeep } from 'lodash-es'
	import ExcelJS from 'exceljs'
	import bizHistoryExcelApi from '@/api/biz/bizHistoryExcelApi'
	import '@vue-office/docx/lib/index.css'
	import { message } from 'ant-design-vue'
	import stox from './xlsxspread'

	import { read } from 'xlsx'
	import { useTemplateRef } from 'vue'
	import { exceljsToXSpread, parseTableWithMerges } from '@/views/biz/bizhistoryexcel/exceljsToXSpread'

	//
	//Spreadsheet.locale('zh-cn', zhCN)
	import Spreadsheet from 'x-data-spreadsheet'
	import 'x-data-spreadsheet/dist/locale/zh-cn'

	Spreadsheet.locale('zh-cn')
	// 抽屉状态
	const open = ref(false)
	const emit = defineEmits({ successful: null })
	const formRef = ref()
	// 表单数据
	const formData = ref({})
	const arrayBuffer = ref([])
	const submitLoading = ref(false)

	const renderedHandler = () => {
		loading.value = false
	}
	// 渲染失败
	const errorHandler = () => {
		message.warning('渲染失败，请尝试重新打开！')
	}

	// 自定义上传请求，阻止默认上传行为
	const handleCustomRequest = async ({ file, onSuccess, onError }) => {
		// 这里不执行实际上传，只解析文件
		formData.value.file = file
		const data = await formData.value.file.arrayBuffer()
		const wb = read(data, { cellStyles: true })
		const a = await stox(wb)

		console.log(a)
	}

	// 打开抽屉
	const onOpen = (record) => {
		open.value = true
		if (record) {
			let recordData = cloneDeep(record)
			formData.value = Object.assign({}, recordData)
		}
	}
	let spreadsheet = null
	// 关闭抽屉
	const onClose = () => {
		formRef.value.resetFields()
		formData.value = {}
		open.value = false
	}
	// 默认要校验的
	const formRules = {}

	// 验证并提交数据
	const onSubmit = async () => {
		if (!formData.value.file) {
			message.warning('请上传文件！')
			return
		}
		submitLoading.value = true
		try {
			const data = await formData.value.file.arrayBuffer()
			const res = await exceljsToXSpread(data)

			const extJson = JSON.stringify(res)
			await bizHistoryExcelApi.bizHistoryExcelSubmitForm(
				{
					name: formData.value.file.name,
					extJson
				},
				false
			)
			onClose()
			emit('successful')
		} catch (error) {
			console.error('读取文件失败:', error)
		} finally {
			submitLoading.value = false
		}
	}
	// 抛出函数
	defineExpose({
		onOpen
	})
</script>
