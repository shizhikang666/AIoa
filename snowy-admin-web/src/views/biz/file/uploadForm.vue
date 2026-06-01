<template>
	<xn-form-container
		title="文件上传"
		:width="550"
		:visible="visible"
		:destroy-on-close="true"
		:bodyStyle="{ 'padding-top': '0px' }"
		@close="onClose"
	>
		<a-tabs v-model:activeKey="activeKey">
			<a-tab-pane key="Local" tab="本地">
				<a-spin :spinning="uploadLoading">
					<a-upload-dragger
						:multiple="multiple"
						:accept="accept"
						:show-upload-list="false"
						:custom-request="customRequestLocal"
					>
						<p class="ant-upload-drag-icon">
							<inbox-outlined></inbox-outlined>
						</p>
						<p class="ant-upload-text">单击或拖动文件到此区域进行上传</p>
						<p class="ant-upload-hint">支持多个上传</p>
					</a-upload-dragger>
				</a-spin>
			</a-tab-pane>
			<!--			<a-tab-pane key="Aliyun" tab="阿里云">-->
			<!--				<a-spin :spinning="uploadLoading">-->
			<!--					<a-upload-dragger :custom-request="customRequestAliyun" :show-upload-list="false">-->
			<!--						<p class="ant-upload-drag-icon">-->
			<!--							<inbox-outlined></inbox-outlined>-->
			<!--						</p>-->
			<!--						<p class="ant-upload-text">单击或拖动文件到此区域进行上传</p>-->
			<!--						<p class="ant-upload-hint">支持单个上传</p>-->
			<!--					</a-upload-dragger>-->
			<!--				</a-spin>-->
			<!--			</a-tab-pane>-->
			<!--			<a-tab-pane key="Tencent" tab="腾讯云">-->
			<!--				<a-spin :spinning="uploadLoading">-->
			<!--					<a-upload-dragger :custom-request="customRequestTencent" :show-upload-list="false">-->
			<!--						<p class="ant-upload-drag-icon">-->
			<!--							<inbox-outlined></inbox-outlined>-->
			<!--						</p>-->
			<!--						<p class="ant-upload-text">单击或拖动文件到此区域进行上传</p>-->
			<!--						<p class="ant-upload-hint">支持单个上传</p>-->
			<!--					</a-upload-dragger>-->
			<!--				</a-spin>-->
			<!--			</a-tab-pane>-->
			<!--			<a-tab-pane key="Minio" tab="MINIO">-->
			<!--				<a-spin :spinning="uploadLoading">-->
			<!--					<a-upload-dragger :custom-request="customRequestMinio" :show-upload-list="false">-->
			<!--						<p class="ant-upload-drag-icon">-->
			<!--							<inbox-outlined></inbox-outlined>-->
			<!--						</p>-->
			<!--						<p class="ant-upload-text">单击或拖动文件到此区域进行上传</p>-->
			<!--						<p class="ant-upload-hint">支持单个上传</p>-->
			<!--					</a-upload-dragger>-->
			<!--				</a-spin>-->
			<!--			</a-tab-pane>-->
		</a-tabs>
	</xn-form-container>
</template>

<script setup name="uploadForm">
	import fileApi from '@/api/dev/fileApi'
	import bizFileRelationApi from '@/api/biz/bizFileRelationApi'

	// 定义emit事件
	const emit = defineEmits({ successful: null })
	// 默认是关闭状态
	const visible = ref(false)
	const activeKey = ref('Local')
	const uploadLoading = ref(false)

	const { accept, multiple, objectId, category } = defineProps({
		accept: {},
		objectId: {
			required: false,
			type: String
		},
		category: {
			required: false,
			type: String
		},
		multiple: {
			type: Boolean,
			default: true
		}
	})

	// 打开抽屉
	const openUpload = () => {
		visible.value = true
	}
	// 关闭抽屉
	const onClose = () => {
		visible.value = false
	}
	// 上传本地文件
	const customRequestLocal = async (data) => {
		uploadLoading.value = true
		const fileData = new FormData()
		fileData.append('file', data.file)
		try {
			const res = await fileApi.fileUploadReturnFile(fileData)
			if (objectId) {
				await bizFileRelationApi.bizFileRelationSubmitForm({
					targetId: res.id,
					objectId,
					category
				})
			}
			emit('successful', res)
		} catch (e) {
		} finally {
			uploadLoading.value = false
		}
	}
	// 上传阿里云文件
	const customRequestAliyun = async (data) => {
		uploadLoading.value = true
		const fileData = new FormData()
		fileData.append('file', data.file)

		try {
			const res = await fileApi.fileUploadAliyunReturnFile(fileData)

			if (objectId) {
				await bizFileRelationApi.bizFileRelationSubmitForm({
					targetId: res.id,
					objectId,
					category
				})
			}

			emit('successful', res)
		} catch (e) {
		} finally {
			uploadLoading.value = false
		}
	}
	// 上传腾讯文件
	const customRequestTencent = async (data) => {
		uploadLoading.value = true
		const fileData = new FormData()
		fileData.append('file', data.file)

		try {
			const res = await fileApi.fileUploadTencentReturnFile(fileData)

			let rela = {}
			if (objectId) {
				rela = await bizFileRelationApi.bizFileRelationSubmitForm({
					targetId: res.id,
					objectId,
					category
				})
			}

			emit('successful', { ...res, rela })
		} catch (e) {
		} finally {
			uploadLoading.value = false
		}
	}
	// 上传Minio文件
	const customRequestMinio = async (data) => {
		uploadLoading.value = true
		const fileData = new FormData()
		fileData.append('file', data.file)
		try {
			const res = await fileApi.fileUploadMinioReturnFile(fileData)
			if (objectId) {
				await bizFileRelationApi.bizFileRelationSubmitForm({
					targetId: res.id,
					objectId,
					category
				})
			}
			emit('successful', res)
		} catch (e) {
		} finally {
			uploadLoading.value = false
		}
	}
	// 调用这个函数将子组件的一些数据和方法暴露出去
	defineExpose({
		openUpload,
		onClose
	})
</script>
