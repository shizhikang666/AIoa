<template>
	<xn-form-container
		title="售后记录详情"
		:width="920"
		v-model:open="open"
		:destroy-on-close="true"
		@close="open = false"
	>
		<a-spin :spinning="loading">
			<a-descriptions bordered size="small" :column="2">
				<a-descriptions-item label="标题" :span="2">{{ detail.title }}</a-descriptions-item>
				<a-descriptions-item label="分类">{{ detail.categoryName }}</a-descriptions-item>
				<a-descriptions-item label="处理时间">{{ detail.handleTime }}</a-descriptions-item>
				<a-descriptions-item label="关联项目">{{ detail.projectName || '未关联' }}</a-descriptions-item>
				<a-descriptions-item label="创建人">{{ detail.createUserName }}</a-descriptions-item>
				<a-descriptions-item label="所属组织">{{ detail.createUserOrgName || '--' }}</a-descriptions-item>
				<a-descriptions-item label="创建时间">{{ detail.createTime }}</a-descriptions-item>
			</a-descriptions>

			<section class="detail-section">
				<h3>售后处理内容</h3>
				<div class="rich-content" v-html="detail.content"></div>
			</section>

			<section v-if="detail.fileList?.length" class="detail-section">
				<h3>附件</h3>
				<a-list size="small" bordered :data-source="detail.fileList">
					<template #renderItem="{ item }">
						<a-list-item>
							<a :href="item.downloadPath" target="_blank" rel="noopener noreferrer">{{ item.name }}</a>
							<span class="file-size">{{ item.sizeKb }} KB</span>
						</a-list-item>
					</template>
				</a-list>
			</section>
		</a-spin>
		<template #footer>
			<a-button @click="open = false">关闭</a-button>
		</template>
	</xn-form-container>
</template>

<script setup name="afterSalesDetail">
	import afterSalesApi from '@/api/biz/afterSalesApi'

	const open = ref(false)
	const loading = ref(false)
	const detail = ref({})

	const onOpen = async (record) => {
		open.value = true
		loading.value = true
		try {
			detail.value = await afterSalesApi.afterSalesDetail({ id: record.id })
		} finally {
			loading.value = false
		}
	}

	defineExpose({ onOpen })
</script>

<style scoped>
	.detail-section {
		margin-top: 22px;
	}

	.detail-section h3 {
		margin-bottom: 10px;
		font-size: 15px;
	}

	.rich-content {
		min-height: 160px;
		padding: 16px;
		border: 1px solid #f0f0f0;
		background: #fff;
		line-height: 1.7;
		overflow-wrap: anywhere;
	}

	.rich-content :deep(img) {
		max-width: 100%;
		height: auto;
	}

	.file-size {
		color: #8c8c8c;
	}
</style>
