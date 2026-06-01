<template>
	<a-descriptions v-if="project.projectName" bordered :title="project.projectName" size="small">
		<a-descriptions-item label="项目名称">{{ project.projectName }}</a-descriptions-item>
		<a-descriptions-item label="项目编号">{{ project.projectCode }}</a-descriptions-item>
		<a-descriptions-item label="项目地区">{{ project.area }}</a-descriptions-item>
		<a-descriptions-item label="项目负责人">
			{{ project.headName }}
		</a-descriptions-item>
		<a-descriptions-item :span="2" label="成交日期">
			{{ project.completionDate }}
		</a-descriptions-item>
		<a-descriptions-item :span="2" label="收货单位">
			{{ project.unit }}
		</a-descriptions-item>
		<a-descriptions-item :span="2" label="收货地址">
			{{ project.address }}
		</a-descriptions-item>
	</a-descriptions>
	<br />
	<!--	<a-card v-if="imgList.length">-->
	<!--		<a-skeleton active :loading="loading">-->
	<!--			<a-button type="primary" @click="() => uploadFormRef.openUpload()">-->
	<!--				<UploadOutlined />-->
	<!--				文件上传-->
	<!--			</a-button>-->
	<!--			<br />-->
	<!--			<br />-->
	<!--			<a-image-preview-group>-->
	<!--				<a-list :grid="{ gutter: 16, xs: 1, sm: 2, md: 4, lg: 4, xl: 6, xxl: 3 }" :data-source="imgList">-->
	<!--					<template #renderItem="{ item }">-->
	<!--						<a-list-item>-->
	<!--							<a-card>-->
	<!--								<a-image width="100%" :src="item.downloadPath" />-->
	<!--							</a-card>-->
	<!--						</a-list-item>-->
	<!--					</template>-->
	<!--				</a-list>-->
	<!--			</a-image-preview-group>-->
	<!--		</a-skeleton>-->
	<!--	</a-card>-->

	<a-list :loading="loading" item-layout="vertical" size="large" :pagination="true" :data-source="listData">
		<template #renderItem="{ item }">
			<a-list-item key="item.title">
				<a-comment>
					<template #author
						><a>{{ item.createUserName }}</a>
					</template>
					<template #avatar>
						<a-avatar :src="item.avatar" :alt="item.createUserName" />
					</template>
					<template #content>
						<a-space direction="vertical">
							<!--							<a-rate disabled v-model:value="item.rateAmount" />-->
							{{ item.subject }}
							<p v-html="item.content"></p>
							<a-image-preview-group>
								<a-image :width="200" v-for="url in item.imgList" :src="url" />
							</a-image-preview-group>
						</a-space>
					</template>
					<template #datetime>
						<a-tooltip :title="item.createTime">
							<span>{{ dayjs(item.createTime).fromNow() }}</span>
						</a-tooltip>
					</template>
				</a-comment>
			</a-list-item>
		</template>
	</a-list>
	<a-comment>
		<template #avatar>
			<a-avatar :src="userInfo.avatar" :alt="userInfo.name" />
		</template>
		<template #content>
			<a-form ref="formRef" :model="formData" :rules="formRules">
				<a-form-item label="类型" name="subject">
					<a-row :span="24">
						<a-col span="6">
							<!--							<a-rate v-model:value="formData.rateAmount" />-->
							<a-select v-model:value="formData.subject" :options="options"></a-select>
						</a-col>
					</a-row>
				</a-form-item>

				<a-form-item label="图片">
					<div class="clearfix">
						<a-upload
							v-model:file-list="fileList"
							:multiple="true"
							:custom-request="uploadDynamicReturnFile"
							list-type="picture-card"
							@preview="handlePreview"
						>
							<div>
								<plus-outlined />
								<div style="margin-top: 8px">上传图片</div>
							</div>
						</a-upload>
						<a-modal :open="previewVisible" :title="previewTitle" :footer="null" @cancel="handleCancel">
							<img alt="example" style="width: 100%" :src="previewImage" />
						</a-modal>
					</div>
				</a-form-item>

				<a-form-item label="" name="content">
					<a-textarea v-model:value="formData.content" :rows="4" />
				</a-form-item>
				<a-form-item label="">
					<a-button html-type="submit" :loading="submitLoading" type="primary" @click="onSubmit"> 添加 </a-button>
				</a-form-item>
			</a-form>
		</template>
	</a-comment>

	<uploadForm
		:accept="'image/*'"
		ref="uploadFormRef"
		:object-id="projectId"
		:category="'SALE_PROJECT_CASE'"
		@successful="load"
	/>
</template>
<script setup name="projectCase">
	import BizFileRelationApi from '@/api/biz/bizFileRelationApi'
	import { useLoading } from '@/composables/useLoading'
	import UploadForm from '@/views/biz/file/uploadForm.vue'
	import { required } from '@/utils/formRules'
	import tool from '@/utils/tool'
	import SaleProjectRateApi from '@/api/biz/saleProjectRateApi'
	import { cloneDeep } from 'lodash-es'
	import dayjs from 'dayjs'
	import { useTemplateRef } from 'vue'
	import fileApi from '@/api/dev/fileApi'
	import bizFileRelationApi from '@/api/biz/bizFileRelationApi'

	const userInfo = tool.data.get('USER_INFO')
	const uploadFormRef = ref()

	const { projectId, project } = defineProps({
		projectId: {
			required: true,
			type: String
		},
		project: {
			type: Object,
			default() {
				return {}
			}
		}
	})
	const options = ref([
		{
			value: '案例过程跟进说明(每月跟进一次，直至完成案例上传)',
			label: '案例过程跟进说明(每月跟进一次，直至完成案例上传)'
		},
		{
			value: '客户反馈',
			label: '客户反馈'
		}
	])

	const formRef = useTemplateRef('formRef')
	const listData = ref([])
	const imgList = ref([])
	const { load, loading, error } = useLoading(async () => {
		const res = await BizFileRelationApi.bizFileRelationList({
			objectId: projectId,
			category: 'SALE_PROJECT_CASE'
		})
		imgList.value = res.map((v) => {
			return {
				...v,
				downloadPath: v.downloadPath.replace('http://47.95.5.233:7971/', 'https://oa.zhixinxinli888.com/backend/')
			}
		})

		await loadRate()
	})
	const loadRate = async () => {
		listData.value = await SaleProjectRateApi.list({
			projectId
		})
	}
	const formRules = {
		// rateAmount: [required('请输入评分')]
	}

	const formData = ref({
		subject: '客户反馈'
	})

	const { loading: submitLoading, load: onSubmit } = useLoading(async () => {
		try {
			await formRef.value.validate()
		} catch (error) {
			console.error(error)
			return
		}
		const formDataParam = cloneDeep(formData.value)

		const imgList = fileList.value
			.filter((value) => value.response)
			.map(({ response }) => {
				return response.downloadPath.replace('http://47.95.5.233:7971/', 'https://oa.zhixinxinli888.com/backend/')
			})

		await SaleProjectRateApi.saleProjectRateSubmitForm({ ...formDataParam, projectId, imgList })
		formData.value = {}
		fileList.value = []
		await loadRate()
	})

	function getBase64(file) {
		return new Promise((resolve, reject) => {
			const reader = new FileReader()
			reader.readAsDataURL(file)
			reader.onload = () => resolve(reader.result)
			reader.onerror = (error) => reject(error)
		})
	}

	const previewVisible = ref(false)
	const previewImage = ref('')
	const previewTitle = ref('')
	const fileList = ref([])
	const handleCancel = () => {
		previewVisible.value = false
		previewTitle.value = ''
	}
	const handlePreview = async (file) => {
		if (!file.url && !file.preview) {
			file.preview = await getBase64(file.originFileObj)
		}
		previewImage.value = file.url || file.preview
		previewVisible.value = true
		previewTitle.value = file.name || file.url.substring(file.url.lastIndexOf('/') + 1)
	}
	// 动态上传文件
	const uploadDynamicReturnFile = async (data) => {
		const fileData = new FormData()
		console.log(data)
		fileData.append('file', data.file)
		try {
			const res = await fileApi.fileUploadDynamicReturnFile(fileData)
			data.onSuccess(res)
		} catch (e) {
			data.onError(e)
		}
	}
	load()
</script>
<style scoped></style>
