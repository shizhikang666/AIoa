<template>
	<a-comment>
		<template #author
			><a>{{ item.createUserName }}</a></template
		>
		<template #content>
			<!--			<p>创建时间：{{ item.createTime }}</p>-->

			<a-space direction="vertical">
				<p v-if="isImage">
					<a-image style="height: 50px" :src="item.downloadPath"></a-image>
				</p>
				<p v-else>
					<a-space>
						<span> 文件名称：{{ item.name }}</span>
						<span>大小：{{ item.sizeInfo }} </span>
					</a-space>
				</p>
				<p>
					<a-space>
						<a-typography-link v-if="!isImage" :href="item.downloadPath">下载</a-typography-link>
						<a-typography-link v-if="!isImage" @click="openFilePreview(item)">预览</a-typography-link>
						<a-typography-link v-if="showRemove" type="danger" @click="emit('remove')">删除 </a-typography-link>
					</a-space>
				</p>
			</a-space>
		</template>
		<template #datetime>
			<a-tooltip :title="item.createTime">
				<span>{{ dayjs(item.createTime).fromNow() }}</span>
			</a-tooltip>
		</template>
	</a-comment>
</template>
<script setup lang="ts">
	import { openFilePreview } from '@/utils/filePreview'
	import dayjs from 'dayjs'

	const { item } = defineProps({
		item: {
			type: Object,
			default: () => ({})
		},
		showRemove: {
			type: Boolean,
			default: true
		}
	})
	const emit = defineEmits({ remove: null })

	const imgSuffix = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'webp']
	const isImage = computed(() => {
		return imgSuffix.includes(item.suffix)
	})
</script>

<style scoped></style>
