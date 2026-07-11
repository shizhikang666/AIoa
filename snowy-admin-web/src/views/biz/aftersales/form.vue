<template>
	<xn-form-container
		:title="formData.id ? '编辑售后记录' : '新增售后记录'"
		:width="920"
		v-model:open="open"
		:destroy-on-close="true"
		@close="onClose"
	>
		<a-spin :spinning="loading">
			<a-form ref="formRef" :model="formData" :rules="formRules" layout="vertical">
				<a-row :gutter="16">
					<a-col :span="12">
						<a-form-item label="售后分类" name="categoryId">
							<a-select v-model:value="formData.categoryId" :options="categoryOptions" placeholder="请选择分类" />
						</a-form-item>
					</a-col>
					<a-col :span="12">
						<a-form-item label="处理时间" name="handleTime">
							<a-date-picker
								v-model:value="formData.handleTime"
								value-format="YYYY-MM-DD HH:mm:ss"
								show-time
								style="width: 100%"
							/>
						</a-form-item>
					</a-col>
				</a-row>
				<a-form-item label="标题" name="title">
					<a-input v-model:value="formData.title" :maxlength="200" show-count placeholder="请输入售后事项标题" />
				</a-form-item>
				<a-form-item label="关联销售项目（可选）" name="projectId">
					<a-select
						v-model:value="formData.projectId"
						show-search
						allow-clear
						:filter-option="false"
						:options="projectOptions"
						:loading="projectLoading"
						placeholder="输入项目名称搜索"
						@search="loadProjects"
					/>
				</a-form-item>
				<a-form-item label="售后处理内容" name="content">
					<xn-editor v-model="formData.content" :height="390" placeholder="填写处理经过、结论，可直接插入图片" />
				</a-form-item>
				<a-form-item label="附件">
					<xn-upload
						:value="formData.fileIdList"
						upload-mode="drag"
						upload-result-type="id"
						upload-result-category="array"
						:upload-number="20"
						upload-text="上传附件"
						@onChange="onFilesChange"
					/>
				</a-form-item>
			</a-form>
		</a-spin>
		<template #footer>
			<a-button class="xn-mr8" @click="onClose">关闭</a-button>
			<a-button type="primary" :loading="submitLoading" @click="onSubmit">保存</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="afterSalesForm">
	import dayjs from 'dayjs'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import afterSalesApi from '@/api/biz/afterSalesApi'
	import bizSaleProjectApi from '@/api/biz/bizSaleProjectApi'

	const emit = defineEmits({ successful: null })
	const open = ref(false)
	const loading = ref(false)
	const submitLoading = ref(false)
	const projectLoading = ref(false)
	const formRef = ref()
	const formData = ref({})
	const categoryOptions = ref([])
	const projectOptions = ref([])
	const formRules = {
		categoryId: [required('请选择售后分类')],
		title: [required('请输入标题')],
		handleTime: [required('请选择处理时间')],
		content: [required('请输入售后处理内容')]
	}

	const loadCategories = async () => {
		const data = await afterSalesApi.categoryList()
		categoryOptions.value = data.map((item) => ({ label: item.name, value: item.id }))
	}

	const loadProjects = async (keyword = '') => {
		projectLoading.value = true
		try {
			const data = await bizSaleProjectApi.bizSaleProjectPage({ current: 1, size: 20, projectName: keyword })
			projectOptions.value = data.records.map((item) => ({ label: item.projectName, value: item.id }))
		} finally {
			projectLoading.value = false
		}
	}

	const onOpen = async (record) => {
		open.value = true
		loading.value = true
		try {
			await Promise.all([loadCategories(), loadProjects()])
			if (record?.id) {
				const detail = await afterSalesApi.afterSalesDetail({ id: record.id })
				formData.value = cloneDeep(detail)
				if (detail.projectId && !projectOptions.value.some((item) => item.value === detail.projectId)) {
					projectOptions.value.unshift({ label: detail.projectName || detail.projectId, value: detail.projectId })
				}
			} else {
				formData.value = {
					categoryId: categoryOptions.value[0]?.value,
					handleTime: dayjs().format('YYYY-MM-DD HH:mm:ss'),
					projectId: undefined,
					title: '',
					content: '',
					fileIdList: []
				}
			}
		} finally {
			loading.value = false
		}
	}

	const onFilesChange = (value) => {
		formData.value.fileIdList = value?.value ?? value ?? []
	}

	const onClose = () => {
		formData.value = {}
		open.value = false
	}

	const onSubmit = async () => {
		await formRef.value.validate()
		submitLoading.value = true
		try {
			const params = cloneDeep(formData.value)
			params.fileIdList = Array.isArray(params.fileIdList) ? params.fileIdList : []
			await afterSalesApi.afterSalesSubmit(params, Boolean(params.id))
			onClose()
			emit('successful')
		} finally {
			submitLoading.value = false
		}
	}

	defineExpose({ onOpen })
</script>
