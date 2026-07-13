<template name="projectFile">
	<a-space>
		<a-button type="primary" @click="() => uploadFormRef.openUpload()">
			<UploadOutlined />
			文件上传
		</a-button>
	</a-space>
	<a-skeleton active :loading="loading">
		<a-list item-layout="horizontal" :data-source="list">
			<template #renderItem="{ item }">
				<a-list-item key="item.id">
					<FileViewItem :show-remove="false" :item="item" @remove="list.splice(index, 1)"></FileViewItem>
				</a-list-item>
			</template>
		</a-list>
	</a-skeleton>
	<uploadForm ref="uploadFormRef" :object-id="projectId" :category="'SALE_PROJECT'" @successful="onSuccess" />
</template>

<script setup lang="js">
	import dayjs from 'dayjs'
	import zhCn from 'dayjs/locale/zh-cn'
	import relativeTime from 'dayjs/plugin/relativeTime'
	import { openFilePreview } from '@/utils/filePreview'

	dayjs.extend(relativeTime)
	// 设置中文显示
	dayjs.locale(zhCn)
	import UploadForm from '@/views/biz/file/uploadForm.vue'
	import BizFileRelationApi from '@/api/biz/bizFileRelationApi'
	import FileViewItem from '@/components/File/FileViewItem.vue'

	const uploadFormRef = ref()

	const props = defineProps({
		projectId: {
			type: String,
			required: true
		}
	})
	const loading = ref(false)

	const list = ref([])
	const load = async () => {
		loading.value = true
		try {
			const res = await BizFileRelationApi.bizFileRelationList({
				objectId: props.projectId,
				category: 'SALE_PROJECT'
			})
			list.value = res
		} finally {
			loading.value = false
		}
	}

	load()

	const onSuccess = () => {
		load()
	}
</script>

<style scoped></style>
