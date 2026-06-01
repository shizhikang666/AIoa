<template>
	<xn-form-container title="导入导出" :width="700" :visible="visible" :destroy-on-close="true" @close="onClose">
		<span
			>导入数据格式严格按照系统模板进行数据录入，请点击
			<a-button type="primary" size="small" @click="downloadImportUserTemplate">下载模板</a-button>
		</span>
		<a-divider dashed />
		<div>
			<a-form-item label="所属组织" name="orgId">
				<a-tree-select
					v-model:value="orgId"
					class="xn-wd"
					:dropdown-style="{ maxHeight: '400px', overflow: 'auto' }"
					placeholder="请选择组织"
					allow-clear
					:tree-data="treeData"
					:field-names="{
						children: 'children',
						label: 'name',
						value: 'id'
					}"
					selectable="false"
					tree-line
				></a-tree-select>
			</a-form-item>

			<a-spin :spinning="impUploadLoading">
				<a-upload-dragger :show-upload-list="false" :custom-request="customRequestLocal" :accept="uploadAccept">
					<p class="ant-upload-drag-icon">
						<inbox-outlined></inbox-outlined>
					</p>
					<p class="ant-upload-text">单击或拖动文件到此区域进行上传</p>
					<p class="ant-upload-hint">仅支持xls、xlsx格式文件</p>
				</a-upload-dragger>
			</a-spin>
		</div>
		<a-alert v-if="impAlertStatus" type="info" :show-icon="false" banner closable @close="onImpClose" class="mt-3">
			<template #description>
				<p>导入总数：{{ impResultData.totalCount }} 条</p>
				<p>导入成功：{{ impResultData.successCount }} 条</p>
				<div v-if="impResultData.errorCount > 0">
					<p><span class="xn-color-red">失败条数：</span>{{ impResultData.errorCount }} 条</p>
					<a-table :dataSource="impResultErrorDataSource" :columns="impErrorColumns" size="small" />
				</div>
			</template>
		</a-alert>
	</xn-form-container>
</template>

<script setup name="payrollImpExp">
	import { message } from 'ant-design-vue'
	import bizPayrollApi from '@/api/biz/bizPayrollApi'
	import downloadUtil from '@/utils/downloadUtil'
	import { useOrg } from '@/composables/useOrg'

	const { treeData, loadingTreeData } = useOrg()
	loadingTreeData().then()
	const impUploadLoading = ref(false)
	const impAlertStatus = ref(false)
	const impResultData = ref({})
	const impResultErrorDataSource = ref([])
	const orgId = ref('')

	const emit = defineEmits({ successful: null })
	const impAccept = [
		{
			extension: '.xls',
			mimeType: 'application/vnd.ms-excel'
		},
		{
			extension: '.xlsx',
			mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		}
	]
	// 指定能选择的文件类型
	const uploadAccept = String(
		impAccept.map((item) => {
			return item.mimeType
		})
	)
	// 导入
	const customRequestLocal = (data) => {
		impUploadLoading.value = true
		const fileData = new FormData()
		// 校验上传文件扩展名和文件类型是否为.xls、.xlsx
		const extension = '.'.concat(data.file.name.split('.').slice(-1).toString().toLowerCase())
		const mimeType = data.file.type
		// 提取允许的扩展名
		const extensionArr = impAccept.map((item) => item.extension)
		// 提取允许的MIMEType
		const mimeTypeArr = impAccept.map((item) => item.mimeType)
		if (!extensionArr.includes(extension) || !mimeTypeArr.includes(mimeType)) {
			message.warning('上传文件类型仅支持xls、xlsx格式文件！')
			impUploadLoading.value = false
			return false
		}
		fileData.append('file', data.file)
		fileData.append('orgId', orgId.value ? orgId.value : '')
		return bizPayrollApi
			.importExcel(fileData)
			.then((res) => {
				impAlertStatus.value = true
				impResultData.value = res
				impResultErrorDataSource.value = res.errorDetail
				emit('successful')
			})
			.finally(() => {
				impUploadLoading.value = false
			})
	}
	// 关闭导入提示
	const onImpClose = () => {
		impAlertStatus.value = false
	}
	const impErrorColumns = [
		{
			title: '索引',
			dataIndex: 'index',
			width: '80px'
		},
		{
			title: '原因',
			dataIndex: 'msg'
		}
	]
	// 定义emit事件

	// 默认是关闭状态
	const visible = ref(false)
	const submitLoading = ref(false)

	// 打开抽屉
	const onOpen = () => {
		visible.value = true
	}
	// 关闭抽屉
	const onClose = () => {
		visible.value = false
		// 关闭导入的提示
		onImpClose()
	}
	// 下载用户导入模板
	const downloadImportUserTemplate = () => {
		bizPayrollApi.downloadImportTemplate().then((res) => {
			downloadUtil.resultDownload(res)
		})
	}
	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		onOpen
	})
</script>
<style scoped>
	.xn-color-red {
		color: #ff0000;
	}
</style>
