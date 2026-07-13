<template>
	<a-list :loading="loading" item-layout="vertical" size="large" :pagination="pagination" :data-source="listData">
		<template #renderItem="{ item }">
			<a-list-item key="item.title">
				<a-comment>
					<template #author
						><a>{{ item.createUserName }}</a></template
					>
					<template #avatar>
						<a-avatar :src="item.avatar" :alt="item.createUserName" />
					</template>
					<template #content>
						<p>
							跟进日期：{{ item.followUpTime }}
							<a-divider type="vertical" />
							跟进类型：
							{{ $TOOL.dictTypeDataByPath('SALE_PROJECT', 'FOLLOW_UP_CATEGORY', item.category) }}
						</p>
						<p v-html="item.content"></p>
						<div class="mini-upload-list" v-if="item.fileList.length > 0">
							<div v-for="(file, index) in item.fileList" :key="file.uid" class="mini-upload-item">
								<span class="file-name">{{ file.name }}</span>

								<a-typography-link :href="file.downloadPath">
									<CloudDownloadOutlined />
								</a-typography-link>
							</div>
						</div>
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
				<a-form-item label="跟进时间：" name="followUpTime">
					<a-row :span="24">
						<a-col span="6">
							<a-date-picker
								v-model:value="formData.followUpTime"
								value-format="YYYY-MM-DD HH:mm:ss"
								show-time
								placeholder="请选择跟进时间"
								style="width: 100%"
							/>
						</a-col>
					</a-row>
				</a-form-item>
				<a-form-item label="跟进类型：" name="category">
					<a-row :span="24">
						<a-col span="6">
							<a-select v-model:value="formData.category" placeholder="请选择跟进类型" :options="categoryOptions" />
						</a-col>
					</a-row>
				</a-form-item>
				<a-form-item label="" name="fileList">
					<div class="mini-upload-list" v-if="formData.fileList.length > 0">
						<div v-for="(file, index) in formData.fileList" :key="file.uid" class="mini-upload-item">
							<span class="file-name">{{ file.name }}</span>
							<a-button type="text" size="small" class="delete-btn">
								<delete-outlined @click="formData.fileList.splice(index, 1)" />
							</a-button>
						</div>
					</div>
				</a-form-item>
				<a-form-item label="" name="content">
					<a-textarea v-model:value="formData.content" :rows="4" />
				</a-form-item>
				<a-form-item label="">
					<a-space>
						<a-button html-type="submit" :loading="submitLoading" type="primary" @click="onSubmit">
							添加跟进记录
						</a-button>
						<a-button type="text" @click="() => uploadFormRef.openUpload()">
							<UploadOutlined />
						</a-button>
					</a-space>
				</a-form-item>
			</a-form>
		</template>
	</a-comment>
	<uploadForm ref="uploadFormRef" :category="'SALE_PROJECT_follow_up'" @successful="onSuccess" />
</template>
<script setup>
	import { ref } from 'vue'
	import saleProjectFollowUpApi from '@/api/biz/saleProjectFollowUpApi'
	import { cloneDeep } from 'lodash-es'
	import { required } from '@/utils/formRules'
	import tool from '@/utils/tool'
	import { safeJsonParse } from '@/utils/json'
	import { normalizeFileUrl } from '@/utils/fileUrl'

	const uploadFormRef = ref()
	const pops = defineProps({
		projectId: {
			type: String,
			required: true
		}
	})
	const userInfo = tool.data.get('USER_INFO')
	const loading = ref(false)
	const error = ref(false)
	const pagination = {
		onChange: (page) => {
			loadData(page)
		},
		pageSize: 5,
		current: 1
	}
	const listData = ref([])
	import dayjs from 'dayjs'
	import zhCn from 'dayjs/locale/zh-cn'
	import relativeTime from 'dayjs/plugin/relativeTime'
	import UploadForm from '@/views/biz/file/uploadForm.vue'

	dayjs.extend(relativeTime)
	// 设置中文显示
	dayjs.locale(zhCn)

	const categoryOptions = ref([])
	categoryOptions.value = tool.dictListByPath('SALE_PROJECT', 'FOLLOW_UP_CATEGORY')
	const formData = ref({
		fileList: [],
		followUpTime: '',
		content: ''
	})
	const formRef = ref()
	// 默认要校验的
	const formRules = {
		followUpTime: [required('请选择跟进时间')],
		category: [required('请选择跟进分类')],
		content: [required('请输入跟进内容')]
	}
	const submitLoading = ref(false)

	const loadData = async (page) => {
		try {
			loading.value = true
			const res = await saleProjectFollowUpApi.saleProjectFollowUpPage(
				Object.assign({
					projectId: pops.projectId,
					size: 5,
					current: page,
					sortField: 'createTime',
					sortOrder: 'descend'
				})
			)
			pagination.current = res.current
			pagination.total = res.total
			listData.value = res.records.map((v) => {
				if (v.extJson) {
					let obj = safeJsonParse(v.extJson, {})
					if (obj.fileList) {
						v.fileList = obj.fileList.map((file) => ({
							...file,
							downloadPath: normalizeFileUrl(file.downloadPath)
						}))
					} else {
						v.fileList = []
					}
				} else {
					v.fileList = []
				}
				return v
			})
		} catch (error) {
			error.value = true
		} finally {
			loading.value = false
		}
	}
	// 验证并提交数据
	const onSubmit = () => {
		formRef.value
			.validate()
			.then(() => {
				submitLoading.value = true
				const formDataParam = cloneDeep(formData.value)
				saleProjectFollowUpApi
					.saleProjectFollowUpSubmitForm({ ...formDataParam, projectId: pops.projectId }, formDataParam.id)
					.then(() => {
						loadData(pagination.current)
						formRef.value.resetFields()
					})
					.finally(() => {
						submitLoading.value = false
					})
			})
			.catch(() => {})
	}

	const onSuccess = (res) => {
		if (!formData.value.fileList) {
			formData.value.fileList = []
		}
		formData.value.fileList.push(res)
	}

	loadData(1)
</script>

<style scoped>
	.mini-upload-list {
		margin-top: 8px;
		max-height: 150px;
		overflow-y: auto;
		display: flex;
	}

	.mini-upload-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 4px 8px;
		font-size: 12px;
		border: 1px solid rgba(217, 217, 217, 0.51);
		border-radius: 2px;
		margin-bottom: 4px;
		//background-color: #fafafa;
		margin-right: 10px;
	}

	.file-name {
		flex: 1;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
		margin-right: 8px;
	}

	.delete-btn {
		color: #ff4d4f;
		font-size: 12px;
	}
</style>
