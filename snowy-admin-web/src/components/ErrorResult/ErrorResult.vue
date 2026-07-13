<script setup lang="js">
	const { onButtonClick, errorButtonText } = defineProps({
		onButtonClick: {
			type: Function,
			default: () => console.log('Button clicked')
		},
		errorText: {
			type: String,
			default: () => '服务器异常'
		},
		errorButtonText: {
			type: String,
			default: () => '重新加载'
		}
	})
	const emit = defineEmits(['reload'])

	const onReload = () => {
		onButtonClick ? onButtonClick() : ''
		emit('reload')
	}

	//成交项目数量
	const dealCount = computed(() => {})
</script>

<template>
	<a-result status="500" title="500" :sub-title="errorText">
		<template #extra>
			<a-button type="primary" @click="onReload">{{ errorButtonText }}</a-button>
		</template>
	</a-result>
</template>

<style scoped></style>
